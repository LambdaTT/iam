<?php

namespace Iam\Commands;

use SplitPHP\Cli;
use SplitPHP\Database\Dao;
use SplitPHP\Utils;

class Setup extends Cli
{
  private static string $SETTINGS_FILEPATH;

  public function init(): void
  {
    self::$SETTINGS_FILEPATH = ROOT_PATH . getenv('IAM_SETTINGS_FILEPATH');

    $this->addCommand('', function ($args) {
      if (self::$SETTINGS_FILEPATH == ROOT_PATH) {
        Utils::printLn("IAM_SETTINGS_FILEPATH environment variable is not defined.");
        return;
      }

      $settings = json_decode(file_get_contents(self::$SETTINGS_FILEPATH), true);

      if (json_last_error() !== JSON_ERROR_NONE) {
        Utils::printLn("\033[91m>> ❌ Failed to parse IAM settings file: " . json_last_error_msg() . "\033[0m");
        return;
      }

      Utils::printLn("\033[36m>> 🚀 Starting IAM Setup...\033[0m");
      Utils::printLn();

      // ------------------------------------------------------------------ //
      // 1. Process Permissions (catalog)                                    //
      // Each entry: { "tag": "orders.manage", "title": "Manage Orders" }    //
      // ------------------------------------------------------------------ //
      if (!empty($settings['permissions'])) {
        Utils::printLn("\033[36m>> 🔐 Processing Permissions...\033[0m");
        foreach ($settings['permissions'] as $permData) {
          $prm = $this->upsertPermission($permData);
          if ($prm) {
            Utils::printLn("   \033[92m✔ Permission '\033[34m{$prm->ds_tag}\033[92m' ready.\033[0m");
          }
        }
        Utils::printLn();
      }

      // Flush so profile permission resolution below (by tag / '*') sees the
      // freshly-upserted IAM_PERMISSION rows:
      Dao::flush();

      // ------------------------------------------------------------------ //
      // 2. Process Teams                                                     //
      // ------------------------------------------------------------------ //
      if (!empty($settings['teams'])) {
        Utils::printLn("\033[36m>> 🛡️  Processing Teams...\033[0m");
        foreach ($settings['teams'] as $teamData) {
          $this->processTeam($teamData);
        }
        Utils::printLn();
      }

      // ------------------------------------------------------------------ //
      // 3. Process Access Profiles                                           //
      // ------------------------------------------------------------------ //
      if (!empty($settings['accessprofiles'])) {
        Utils::printLn("\033[36m>> 👤 Processing Access Profiles...\033[0m");
        foreach ($settings['accessprofiles'] as $profileData) {
          $this->processAccessProfile($profileData);
        }
        Utils::printLn();
      }

      Dao::flush();

      // ------------------------------------------------------------------ //
      // 4. Process Users                                                     //
      // ------------------------------------------------------------------ //
      if (!empty($settings['users'])) {
        Utils::printLn("\033[36m>> 👥 Processing Users...\033[0m");
        foreach ($settings['users'] as $userData) {
          $this->processUser($userData);
        }
        Utils::printLn();
      }

      Utils::printLn("\033[92m>> ✅ IAM Setup completed successfully!\033[0m");
    });
  }

  // ======================================================================== //
  // Private Helpers                                                           //
  // ======================================================================== //

  /**
   * Resolve the plain-text password spec from the JSON.
   * Supports:
   *   - "generate(sha256, uniqid)"  → sha256 hash of uniqid()
   *   - any other string            → used as-is (raw password)
   */
  private function resolvePassword(string $spec): string
  {
    if (preg_match('/^generate\((\w+),\s*(\w+)\)$/i', trim($spec), $m)) {
      $algo  = strtolower($m[1]);
      $input = strtolower($m[2]);
      $value = ($input === 'uniqid') ? uniqid('', true) : $input;
      return hash($algo, $value);
    }
    return $spec;
  }

  /**
   * Upsert a permission record into IAM_PERMISSION.
   * ds_tag is the natural unique identifier; ds_title defaults to the tag.
   * Each entry: { "tag": "orders.manage", "title": "Manage Orders" }
   */
  private function upsertPermission(array $data): ?object
  {
    $tag = isset($data['tag']) ? trim($data['tag']) : '';
    if ($tag === '') {
      Utils::printLn("   \033[93m⚠ Skipping permission without 'tag'.\033[0m");
      return null;
    }

    $title = !empty($data['title']) ? $data['title'] : $tag;

    $existing = $this->getDao('IAM_PERMISSION')
      ->filter('ds_tag')->equalsTo($tag)
      ->first("SELECT * FROM `IAM_PERMISSION` WHERE ds_tag = ?ds_tag?");

    if ($existing) {
      // Keep the title in sync with the config:
      if ($existing->ds_title !== $title) {
        $this->getDao('IAM_PERMISSION')
          ->filter('id_iam_permission')->equalsTo($existing->id_iam_permission)
          ->update(['ds_title' => $title]);
        $existing->ds_title = $title;
      }
      return $existing;
    }

    return $this->getDao('IAM_PERMISSION')->insert([
      'ds_title' => $title,
      'ds_tag'   => $tag,
    ]);
  }

  /**
   * Upsert a user team.
   */
  private function processTeam(array $teamData): void
  {
    $tag   = $teamData['tag']   ?? null;
    $name  = $teamData['name']  ?? ($tag ?? 'Unnamed Team');

    Utils::printLn("   \033[36m↳ Team '\033[34m{$name}\033[36m'...\033[0m");

    $team = null;
    if ($tag) {
      $team = $this->getDao('IAM_USERTEAM')
        ->filter('ds_tag')->equalsTo($tag)
        ->first("SELECT * FROM `IAM_USERTEAM` WHERE ds_tag = ?ds_tag?");
    }

    if (!$team) {
      $team = $this->getDao('IAM_USERTEAM')->insert([
        'ds_name'        => $name,
        'ds_tag'         => $tag,
        'tx_description' => $teamData['description'] ?? null,
      ]);
      Utils::printLn("     \033[92m✔ Created team.\033[0m");
    } else {
      $updData = [];
      if (!empty($teamData['description'])) $updData['tx_description'] = $teamData['description'];
      if (!empty($teamData['name']) && $team->ds_name !== $teamData['name']) $updData['ds_name'] = $teamData['name'];

      if (!empty($updData)) {
        $this->getDao('IAM_USERTEAM')
          ->filter('id_iam_userteam')->equalsTo($team->id_iam_userteam)
          ->update($updData);
      }
      Utils::printLn("     \033[93m↺ Team already exists, updating.\033[0m");
    }
  }

  /**
   * Upsert an access profile and sync its permission associations.
   *
   * profileData['permissions'] is a declarative desired-state:
   *   - array of permission tags → ["orders.manage", "users.read"]
   *   - "*"                      → every permission known to IAM_PERMISSION
   * When the key is present, the profile ends up associated with EXACTLY the
   * resolved set (missing ones are attached, extras are detached).
   */
  private function processAccessProfile(array $profileData): void
  {
    $tag   = $profileData['tag']   ?? null;
    $name  = $profileData['name']  ?? ($tag ?? 'Unnamed Profile');

    Utils::printLn("   \033[36m↳ Profile '\033[34m{$name}\033[36m'...\033[0m");

    // Find or create the profile (match by tag, then by title):
    $profile = null;
    if ($tag) {
      $profile = $this->getDao('IAM_ACCESSPROFILE')
        ->filter('ds_tag')->equalsTo($tag)
        ->first("SELECT * FROM `IAM_ACCESSPROFILE` WHERE ds_tag = ?ds_tag?");
    }
    if (!$profile && $name) {
      $profile = $this->getDao('IAM_ACCESSPROFILE')
        ->filter('ds_title')->equalsTo($name)
        ->first("SELECT * FROM `IAM_ACCESSPROFILE` WHERE ds_title = ?ds_title?");
    }

    if (!$profile) {
      $profile = $this->getDao('IAM_ACCESSPROFILE')->insert([
        'ds_key'         => uniqid(),
        'ds_title'       => $name,
        'ds_tag'         => $tag,
        'tx_description' => $profileData['description'] ?? null,
      ]);
      Utils::printLn("     \033[92m✔ Created profile.\033[0m");
    } else {
      // Update description/tag if provided:
      $updData = [];
      if (!empty($profileData['description'])) $updData['tx_description'] = $profileData['description'];
      if ($tag && $profile->ds_tag !== $tag)   $updData['ds_tag'] = $tag;
      if (!empty($updData)) {
        $this->getDao('IAM_ACCESSPROFILE')
          ->filter('id_iam_accessprofile')->equalsTo($profile->id_iam_accessprofile)
          ->update($updData);
      }
      Utils::printLn("     \033[93m↺ Profile already exists, updating.\033[0m");
    }

    // ------------------------------------------------------------------ //
    // Sync permission associations to the declared desired-state          //
    // ------------------------------------------------------------------ //
    if (array_key_exists('permissions', $profileData)) {
      $desiredIds = $this->resolvePermissionIds($profileData['permissions']);
      [$attached, $detached] = $this->syncProfilePermissions($profile->id_iam_accessprofile, $desiredIds);

      Utils::printLn("     \033[92m✔ Permissions synced → \033[34m" . count($desiredIds) . "\033[92m associated"
        . " (\033[34m+{$attached}\033[92m / \033[34m-{$detached}\033[92m).\033[0m");
    }
  }

  /**
   * Resolve a profile's "permissions" spec (array of tags, or "*") into a list
   * of IAM_PERMISSION ids. Unknown tags are reported and skipped.
   */
  private function resolvePermissionIds($spec): array
  {
    // "*" → every known permission:
    if ($spec === '*') {
      $ids = [];
      $this->getDao('IAM_PERMISSION')->fetch(function ($row) use (&$ids) {
        $ids[] = $row->id_iam_permission;
      }, "SELECT id_iam_permission FROM `IAM_PERMISSION`");
      return $ids;
    }

    if (!is_array($spec)) return [];

    $ids = [];
    foreach ($spec as $permTag) {
      if (empty($permTag)) continue;

      $prm = $this->getDao('IAM_PERMISSION')
        ->filter('ds_tag')->equalsTo($permTag)
        ->first("SELECT id_iam_permission FROM `IAM_PERMISSION` WHERE ds_tag = ?ds_tag?");

      if (!$prm) {
        Utils::printLn("     \033[93m⚠ Permission '\033[34m{$permTag}\033[93m' not found. Skipping.\033[0m");
        continue;
      }

      $ids[] = $prm->id_iam_permission;
    }

    return $ids;
  }

  /**
   * Make a profile's IAM_ACCESSPROFILE_PERMISSION associations match $desiredIds
   * exactly: attach missing ones, detach extras. Returns [attachedCount, detachedCount].
   */
  private function syncProfilePermissions(int $profileId, array $desiredIds): array
  {
    // Current associations (permission id → bridge id):
    $current = [];
    $this->getDao('IAM_ACCESSPROFILE_PERMISSION')
      ->filter('id_iam_accessprofile')->equalsTo($profileId)
      ->fetch(function ($row) use (&$current) {
        $current[$row->id_iam_permission] = $row->id_iam_accessprofile_permission;
      }, "SELECT id_iam_accessprofile_permission, id_iam_permission
            FROM `IAM_ACCESSPROFILE_PERMISSION`
           WHERE id_iam_accessprofile = ?id_iam_accessprofile?");

    $attached = 0;
    $detached = 0;

    // Attach missing:
    foreach ($desiredIds as $permId) {
      if (!isset($current[$permId])) {
        $this->getDao('IAM_ACCESSPROFILE_PERMISSION')->insert([
          'id_iam_accessprofile' => $profileId,
          'id_iam_permission'    => $permId,
        ]);
        $attached++;
      }
    }

    // Detach extras (declarative desired-state):
    $desiredSet = array_flip($desiredIds);
    foreach ($current as $permId => $bridgeId) {
      if (!isset($desiredSet[$permId])) {
        $this->getDao('IAM_ACCESSPROFILE_PERMISSION')
          ->filter('id_iam_accessprofile_permission')->equalsTo($bridgeId)
          ->delete();
        $detached++;
      }
    }

    return [$attached, $detached];
  }

  /**
   * Upsert a user and link it to the specified access profiles and teams by tag.
   */
  private function processUser(array $userData): void
  {
    $username  = $userData['username']  ?? null;
    $email     = $userData['email']     ?? ($userData['ds_email'] ?? null);
    $firstName = $userData['first_name'] ?? ($userData['ds_first_name'] ?? 'User');
    $lastName  = $userData['last_name']  ?? ($userData['ds_last_name']  ?? '');

    $identifier = $username ?? $email ?? $firstName;
    Utils::printLn("   \033[36m↳ User '\033[34m{$identifier}\033[36m'...\033[0m");

    // Locate existing user by username or email:
    $user = null;
    if ($username) {
      $user = $this->getDao('IAM_USER')
        ->filter('ds_username')->equalsTo($username)
        ->first("SELECT * FROM `IAM_USER` WHERE ds_username = ?ds_username?");
    }
    if (!$user && $email) {
      $user = $this->getDao('IAM_USER')
        ->filter('ds_email')->equalsTo($email)
        ->first("SELECT * FROM `IAM_USER` WHERE ds_email = ?ds_email?");
    }

    if (!$user) {
      // Resolve password:
      $rawPassword = $userData['password'] ?? $userData['ds_password'] ?? null;
      $password    = $rawPassword ? $this->resolvePassword($rawPassword) : null;

      $toInsert = [
        'ds_key'        => 'usr-' . uniqid(),
        'ds_username'   => $username,
        'ds_email'      => $email,
        'ds_first_name' => $firstName,
        'ds_last_name'  => $lastName,
        'ds_password'   => $password ? password_hash($password, PASSWORD_DEFAULT) : null,
        'do_hidden'     => isset($userData['hidden']) ? strtoupper($userData['hidden']) : 'N',
      ];

      if (isset($userData['session_timeout'])) {
        $toInsert['nr_session_timeout'] = (int) $userData['session_timeout'];
      }

      if (isset($userData['multi_session'])) {
        $toInsert['do_multi_session'] = strtoupper($userData['multi_session']);
      }

      $user = $this->getDao('IAM_USER')->insert($toInsert);
      Utils::printLn("     \033[92m✔ Created user.\033[0m");
    } else {
      // Sync mutable fields that may have changed in the config:
      $updateData = [];
      if (isset($userData['multi_session'])) {
        $updateData['do_multi_session'] = strtoupper($userData['multi_session']);
      }
      if (!empty($updateData)) {
        $this->getDao('IAM_USER')
          ->filter('id_iam_user')->equalsTo($user->id_iam_user)
          ->update($updateData);
      }
      Utils::printLn("     \033[93m↺ User already exists, keeping data.\033[0m");
    }

    // Link to access profiles by tag:
    if (!empty($userData['accessprofile_tags'])) {
      foreach ($userData['accessprofile_tags'] as $tag) {
        $profile = $this->getDao('IAM_ACCESSPROFILE')
          ->filter('ds_tag')->equalsTo($tag)
          ->first("SELECT id_iam_accessprofile FROM `IAM_ACCESSPROFILE` WHERE ds_tag = ?ds_tag?");

        if (!$profile) {
          Utils::printLn("     \033[93m⚠ Access profile with tag '\033[34m{$tag}\033[93m' not found. Skipping.\033[0m");
          continue;
        }

        $alreadyLinked = $this->getDao('IAM_ACCESSPROFILE_USER')
          ->filter('id_iam_user')->equalsTo($user->id_iam_user)
          ->and('id_iam_accessprofile')->equalsTo($profile->id_iam_accessprofile)
          ->first(
            "SELECT id_iam_accessprofile_user
               FROM `IAM_ACCESSPROFILE_USER`
              WHERE id_iam_user = ?id_iam_user?
                AND id_iam_accessprofile = ?id_iam_accessprofile?"
          );

        if (!$alreadyLinked) {
          $this->getDao('IAM_ACCESSPROFILE_USER')->insert([
            'id_iam_user'         => $user->id_iam_user,
            'id_iam_accessprofile' => $profile->id_iam_accessprofile,
          ]);
          Utils::printLn("     \033[92m✔ Linked to profile tag '\033[34m{$tag}\033[92m'.\033[0m");
        } else {
          Utils::printLn("     \033[93m— Already linked to profile tag '\033[34m{$tag}\033[93m'.\033[0m");
        }
      }
    }

    // Link to team tags
    if (!empty($userData['team_tags'])) {
      foreach ($userData['team_tags'] as $tag) {
        $team = $this->getDao('IAM_USERTEAM')
          ->filter('ds_tag')->equalsTo($tag)
          ->first("SELECT id_iam_userteam FROM `IAM_USERTEAM` WHERE ds_tag = ?ds_tag?");

        if (!$team) {
          Utils::printLn("     \033[93m⚠ Team with tag '\033[34m{$tag}\033[93m' not found. Skipping.\033[0m");
          continue;
        }

        $alreadyLinked = $this->getDao('IAM_USERTEAM_USER')
          ->filter('id_iam_user')->equalsTo($user->id_iam_user)
          ->and('id_iam_userteam')->equalsTo($team->id_iam_userteam)
          ->first(
            "SELECT id_iam_userteam_user
               FROM `IAM_USERTEAM_USER`
              WHERE id_iam_user = ?id_iam_user?
                AND id_iam_userteam = ?id_iam_userteam?"
          );

        if (!$alreadyLinked) {
          $this->getDao('IAM_USERTEAM_USER')->insert([
            'ds_key'          => uniqid(),
            'id_iam_user'     => $user->id_iam_user,
            'id_iam_userteam' => $team->id_iam_userteam,
          ]);
          Utils::printLn("     \033[92m✔ Linked to team tag '\033[34m{$tag}\033[92m'.\033[0m");
        } else {
          Utils::printLn("     \033[93m— Already linked to team tag '\033[34m{$tag}\033[93m'.\033[0m");
        }
      }
    }
  }
}
