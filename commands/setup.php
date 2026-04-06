<?php

namespace Iam\Commands;

use SplitPHP\Cli;
use SplitPHP\Database\Dao;
use SplitPHP\Utils;

class Setup extends Cli
{
  private static $SETTINGS_FILEPATH;

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
      // 1. Build a lookup map of all known entities: entity_name → record   //
      // ------------------------------------------------------------------ //
      $entityMap = $this->buildEntityMap();

      // ------------------------------------------------------------------ //
      // 2. Process Custom Permissions                                        //
      // ------------------------------------------------------------------ //
      $customPermissionsById = [];
      if (!empty($settings['customPermissions'])) {
        Utils::printLn("\033[36m>> 🔐 Processing Custom Permissions...\033[0m");
        foreach ($settings['customPermissions'] as $cpData) {
          $cp = $this->upsertCustomPermission($cpData, $entityMap);
          if ($cp) {
            $customPermissionsById[$cpData['ds_key']] = $cp;
            Utils::printLn("   \033[92m✔ Custom permission '\033[34m{$cpData['ds_key']}\033[92m' ready.\033[0m");
          }
        }
        Utils::printLn();
      }

      // ------------------------------------------------------------------ //
      // 3. Process Access Profiles                                           //
      // ------------------------------------------------------------------ //
      if (!empty($settings['accessprofiles'])) {
        Utils::printLn("\033[36m>> 👤 Processing Access Profiles...\033[0m");
        foreach ($settings['accessprofiles'] as $profileData) {
          $this->processAccessProfile($profileData, $entityMap);
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
   * Build a flat map of entity_name → entity record (including id_mdc_module).
   * This avoids repeated DB queries when resolving entity names to their modules.
   */
  private function buildEntityMap(): array
  {
    $map = [];
    $this->getDao('MDC_MODULE_ENTITY')->fetch(function ($row) use (&$map) {
      $map[strtoupper($row->ds_entity_name)] = $row;
    }, "SELECT ent.id_mdc_module_entity, ent.id_mdc_module, ent.ds_entity_name FROM `MDC_MODULE_ENTITY` ent");
    return $map;
  }

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
   * Upsert a custom permission record.
   * ds_key is used as the natural unique identifier.
   * The 'module' field in the JSON must be an entity name whose module will be used.
   */
  private function upsertCustomPermission(array $data, array $entityMap): ?object
  {
    if (empty($data['ds_key'])) {
      Utils::printLn("   \033[93m⚠ Skipping custom permission without 'ds_key'.\033[0m");
      return null;
    }

    // Resolve the module from the entity name, if provided:
    $moduleId = null;
    if (!empty($data['module'])) {
      $entityKey = strtoupper($data['module']);
      if (isset($entityMap[$entityKey])) {
        $moduleId = $entityMap[$entityKey]->id_mdc_module;
      } else {
        // Try looking up directly as a module title:
        $mod = $this->getDao('MDC_MODULE')
          ->filter('ds_title')->equalsTo($data['module'])
          ->first("SELECT id_mdc_module FROM `MDC_MODULE` WHERE ds_title = ?ds_title?");
        $moduleId = $mod ? $mod->id_mdc_module : null;
      }
    }

    $existing = $this->getDao('IAM_CUSTOM_PERMISSION')
      ->filter('ds_key')->equalsTo($data['ds_key'])
      ->first("SELECT * FROM `IAM_CUSTOM_PERMISSION` WHERE ds_key = ?ds_key?");

    if ($existing) {
      // Update title and module if changed:
      $updateData = [];
      if (!empty($data['ds_title'])) $updateData['ds_title'] = $data['ds_title'];
      if ($moduleId !== null)        $updateData['id_mdc_module'] = $moduleId;

      if (!empty($updateData)) {
        $this->getDao('IAM_CUSTOM_PERMISSION')
          ->filter('ds_key')->equalsTo($data['ds_key'])
          ->update($updateData);
      }
      return $existing;
    }

    // Insert:
    $toInsert = [
      'ds_key'      => $data['ds_key'],
      'ds_title'    => $data['ds_title'] ?? $data['ds_key'],
      'id_mdc_module' => $moduleId,
    ];
    return $this->getDao('IAM_CUSTOM_PERMISSION')->insert($toInsert);
  }

  /**
   * Upsert an access profile and apply all its permissions (regular + custom).
   */
  private function processAccessProfile(array $profileData, array $entityMap): void
  {
    $tag   = $profileData['tag']   ?? null;
    $name  = $profileData['name']  ?? ($tag ?? 'Unnamed Profile');

    Utils::printLn("   \033[36m↳ Profile '\033[34m{$name}\033[36m'...\033[0m");

    /** @var \Iam\Services\Accessprofile $profileSvc */
    $profileSvc = $this->getService('iam/accessprofile');

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
        'ds_key'   => uniqid(),
        'ds_title' => $name,
        'ds_tag'   => $tag,
        'tx_description' => $profileData['description'] ?? null,
      ]);
      Utils::printLn("     \033[92m✔ Created profile.\033[0m");
    } else {
      // Update description if provided:
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

    $profileId = $profile->id_iam_accessprofile;

    // ------------------------------------------------------------------ //
    // Apply regular permissions                                            //
    // Each entry: { "ENTITY_NAME": "CRUD_STRING" }                        //
    // ------------------------------------------------------------------ //
    if (!empty($profileData['permissions']['regular'])) {
      // Track which modules were already linked during this run to avoid
      // calling addModule() twice for the same module. The DAO won't see
      // the freshly-inserted row until a flush, so we deduplicate here.
      $linkedModules    = [];
      $configuredEntityIds = []; // track which entities are explicitly configured

      foreach ($profileData['permissions']['regular'] as $permEntry) {
        foreach ($permEntry as $entityName => $operations) {
          $entityKey = strtoupper($entityName);

          if (!isset($entityMap[$entityKey])) {
            Utils::printLn("     \033[93m⚠ Entity '\033[34m{$entityName}\033[93m' not found in entity map. Skipping.\033[0m");
            continue;
          }

          $entity   = $entityMap[$entityKey];
          $moduleId = $entity->id_mdc_module;
          $entityId = $entity->id_mdc_module_entity;

          $configuredEntityIds[] = $entityId;

          // Only call addModule() once per module per profile run:
          if (!in_array($moduleId, $linkedModules)) {
            $profileSvc->addModule($profileId, $moduleId);
            // Flush so the permission rows inserted by addModule() are
            // immediately visible to the SELECT inside applyEntityPermissions():
            Dao::flush();
            $linkedModules[] = $moduleId;
          }

          // Apply the specific CRUD flags for this entity:
          $profileSvc->applyEntityPermissions($profileId, $entityId, $operations);

          Utils::printLn("     \033[92m✔ Entity '\033[34m{$entityName}\033[92m' → \033[34m{$operations}\033[0m");
        }
      }

      // Zero out permission rows for any entity that belongs to one of the
      // linked modules but was NOT mentioned in the config:
      if (!empty($configuredEntityIds)) {
        $allProfilePerms = $this->getDao('IAM_ACCESSPROFILE_PERMISSION')
          ->filter('profile_id')->equalsTo($profileId)
          ->find(
            "SELECT perm.id_iam_accessprofile_permission, perm.id_mdc_module_entity
               FROM `IAM_ACCESSPROFILE_PERMISSION` perm
               JOIN `IAM_ACCESSPROFILE_MODULE` apm ON apm.id_iam_accessprofile_module = perm.id_iam_accessprofile_module
              WHERE apm.id_iam_accessprofile = ?profile_id?"
          );

        foreach ($allProfilePerms as $row) {
          if (!in_array($row->id_mdc_module_entity, $configuredEntityIds)) {
            $this->getDao('IAM_ACCESSPROFILE_PERMISSION')
              ->filter('id_iam_accessprofile_permission')->equalsTo($row->id_iam_accessprofile_permission)
              ->update([
                'do_read'   => 'N',
                'do_create' => 'N',
                'do_update' => 'N',
                'do_delete' => 'N',
              ]);
          }
        }
      }
    }

    // ------------------------------------------------------------------ //
    // Apply custom permissions                                             //
    // Each entry is a ds_key string of a IAM_CUSTOM_PERMISSION            //
    // ------------------------------------------------------------------ //
    if (!empty($profileData['permissions']['custom'])) {
      foreach ($profileData['permissions']['custom'] as $permKey) {
        if (empty($permKey)) continue;

        // Check if already linked:
        $cp = $this->getDao('IAM_CUSTOM_PERMISSION')
          ->filter('ds_key')->equalsTo($permKey)
          ->first("SELECT id_iam_custom_permission FROM `IAM_CUSTOM_PERMISSION` WHERE ds_key = ?ds_key?");

        if (!$cp) {
          Utils::printLn("     \033[93m⚠ Custom permission '\033[34m{$permKey}\033[93m' not found. Skipping.\033[0m");
          continue;
        }

        $alreadyLinked = $this->getDao('IAM_ACCESSPROFILE_CUSTOM_PERMISSION')
          ->filter('id_iam_accessprofile')->equalsTo($profileId)
          ->and('id_iam_custom_permission')->equalsTo($cp->id_iam_custom_permission)
          ->first(
            "SELECT id_iam_accessprofile_custom_permission
               FROM `IAM_ACCESSPROFILE_CUSTOM_PERMISSION`
              WHERE id_iam_accessprofile = ?id_iam_accessprofile?
                AND id_iam_custom_permission = ?id_iam_custom_permission?"
          );

        if (!$alreadyLinked) {
          $this->getDao('IAM_ACCESSPROFILE_CUSTOM_PERMISSION')->insert([
            'id_iam_accessprofile'      => $profileId,
            'id_iam_custom_permission'  => $cp->id_iam_custom_permission,
          ]);
        }
        Utils::printLn("     \033[92m✔ Custom permission '\033[34m{$permKey}\033[92m' linked.\033[0m");
      }
    }
  }

  /**
   * Upsert a user and link it to the specified access profiles by tag.
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

      $user = $this->getDao('IAM_USER')->insert($toInsert);
      Utils::printLn("     \033[92m✔ Created user.\033[0m");
    } else {
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
  }
}
