<?php

namespace Iam\Commands;

use Exception;
use SplitPHP\Cli;
use SplitPHP\Utils;

class Users extends Cli
{
  public function init()
  {
    $this->addCommand('list', function ($args) {
      $getRows = function ($params) {
        return $this->getService('iam/user')->list($params, true);
      };

      $columns = [
        'id_iam_user'              => 'ID',
        'dt_created'               => 'Created At',
        'fullName'                 => 'Name',
        'ds_email'                 => 'Email',
        'do_active'                => 'Active?',
        'ds_avatar_img_url'        => 'Avatar URL',
        'dt_last_access'           => 'Last Access',
        'do_is_superadmin'         => 'Is Superadmin?',
      ];

      $this->getService('utils/misc')->printDataTable("Users List", $getRows, $columns, $args);
    });

    $this->addCommand('create', function ($args) {
      if (in_array('--is-superadmin', $args)) {
        $superadmin = 'Y';
        $hidden = 'Y';
        unset($args['--is-superadmin']);
      } else {
        $superadmin = 'N';
      }

      if (in_array('--is-hidden', $args)) {
        $hidden = 'Y';
        unset($args['--is-hidden']);
      } else {
        $hidden = 'N';
      }

      Utils::printLn("Welcome to the Iam User Create Command!");
      Utils::printLn("This command will help you create a new user in the IAM system.");
      Utils::printLn();
      Utils::printLn(" >> Please follow the prompts to define your user informations.");
      Utils::printLn();
      Utils::printLn("  >> New User:");
      Utils::printLn("------------------------------------------------------");

      $user = $this->getService('utils/clihelper')->inputForm([
        'ds_email' => [
          'label' => 'Email',
          'required' => true,
          'length' => 255,
        ],
        'ds_password' => [
          'label' => 'Password',
          'required' => true,
          'length' => 60,
        ],
        'confirm_password' => [
          'label' => 'Confirm Password',
          'required' => true,
          'length' => 60,
          'validator' => function ($value, $data) {
            if ($value !== $data['ds_password']) {
              return "Passwords do not match.";
            }
            return true;
          },
        ],
        'ds_first_name' => [
          'label' => 'First Name',
          'required' => true,
          'length' => 100,
        ],
        'ds_last_name' => [
          'label' => 'Last Name',
          'required' => true,
          'length' => 100,
        ],
        'ds_phone1' => [
          'label' => 'Phone 1',
          'required' => false,
          'length' => 20,
        ],
        'ds_phone2' => [
          'label' => 'Phone 2',
          'required' => false,
          'length' => 20,
        ],
        'ds_company' => [
          'label' => 'Company',
          'required' => false,
          'length' => 255,
        ],
      ]);

      unset($user->confirm_password); // Remove confirm_password from the final user object
      $user->do_is_superadmin = $superadmin;
      $user->do_hidden = $hidden;

      $this->setUserAvatar($user);

      $record = $this->getService('iam/user')->create($user);

      Utils::printLn("  >> Iam User created successfully!");
      foreach ($record as $key => $value) {
        Utils::printLn("    -> {$key}: {$value}");
      }
    });

    $this->addCommand('generate:superadmin', function () {
      Utils::printLn("  >> Inserting superadmin user...");
      $sa = $this->getService('iam/user')
        ->create([
          'ds_email' => 'system@admin.com',
          'ds_password' => 'Pass123',
          'ds_first_name' => 'Super',
          'ds_last_name' => 'Admin',
          'ds_company' => null,
          'id_fmn_file_avatar' => null,
          'dt_last_access' =>  null,
          'do_active' => 'Y',
          'do_session_expires' => 'N',
          'do_is_superadmin' => 'Y',
          'do_hidden' => 'Y',
        ]);

      Utils::printLn("  >> Superadmin user was inserted successfully.");
      Utils::printLn();
      Utils::printLn("  >> User details:");
      Utils::printLn();
      Utils::printLn("    - ID: " . $sa->id_iam_user);
      Utils::printLn("    - Key: " . $sa->ds_key);
      Utils::printLn("    - Email: " . $sa->ds_email);
      Utils::printLn("    - First Name: " . $sa->ds_first_name);
      Utils::printLn("    - Last Name: " . $sa->ds_last_name);
      Utils::printLn("    - Password: Pass123");
    });

    $this->addCommand('remove', function () {
      Utils::printLn("Welcome to the Iam User Removal Command!");
      Utils::printLn();
      $userId = readline("  >> Please, enter the User ID you want to remove: ");

      $this->getService('iam/user')->remove([
        'id_iam_user' => $userId,
      ]);
      Utils::printLn("  >> User with ID {$userId} removed successfully!");
    });

    $this->addCommand('set:access-profiles', function ($args) {
      if (in_array('--remove', $args)) {
        $remove = true;
        unset($args['--remove']);
      }

      Utils::printLn("Welcome to the Iam User Set Access Profiles Command!");
      Utils::printLn("This command will help you set access profiles for a user in the IAM system.");
      Utils::printLn();
      Utils::printLn(" >> Please follow the prompts to define the user and set its access profiles.");
      Utils::printLn();
      Utils::printLn("  >> Set Access Profiles:");
      Utils::printLn("------------------------------------------------------");

      $userId = readline("    -> Enter User ID: ");
      $profileIds = explode(',', readline("    -> Enter Access Profile IDs (separated by comma): "));
      Utils::printLn();

      $profileIds = array_map('trim', $profileIds);

      if ($remove) {
        Utils::printLn("  >> Removing access profiles for user ID {$userId}...");
        $this->getDao('IAM_ACCESSPROFILE_USER')
          ->filter('id_iam_user')->equalsTo($userId)
          ->and('id_iam_accessprofile')->in($profileIds)
          ->delete();

        Utils::printLn("  >> Access profiles removed successfully for user ID {$userId}!");
        return;
      }

      Utils::printLn("  >> Setting access profiles for user ID {$userId}...");
      $profiles = $this->getDao('IAM_ACCESSPROFILE')
        ->filter('id_iam_accessprofile')->in($profileIds)
        ->find();

      if (empty($profiles)) {
        throw new Exception("No valid access profiles found for the provided IDs.");
        return;
      }

      $this->getService('iam/user')->updUserProfiles($userId, $profiles);

      Utils::printLn("  >> Access profiles set successfully for user ID {$userId}!");
      foreach ($profiles as $profile) {
        Utils::printLn("    -> {$profile->ds_title} (ID: {$profile->id_iam_accessprofile})");
      }
    });

    $this->addCommand('help', function () {
      /** @var \Utils\Services\CliHelper $helper */
      $helper = $this->getService('utils/clihelper');
      Utils::printLn($helper->ansi(strtoupper("Welcome to the Iam User Help Center!"), 'color: magenta; font-weight: bold'));

      // 1) Define metadata for each command
      $commands = [
        'users:list'   => [
          'usage' => 'iam:users:list [--limit=<n>] [--sort-by=<field>] [--sort-direction=<dir>] [--page=<n>]',
          'desc'  => 'Page through existing users.',
          'flags' => [
            '--limit=<n>'          => 'Items per page (default 10)',
            '--sort-by=<field>'    => 'Field to sort by',
            '--sort-direction=<d>' => 'ASC or DESC (default ASC)',
            '--page=<n>'           => 'Page number (default 1)',
          ],
        ],
        'users:create' => [
          'usage' => 'iam:users:create [--is-superadmin] [--is-hidden]',
          'desc'  => 'Interactively create a new user.',
          'flags' => [
            '--is-superadmin'      => 'Whether the user is a superadmin',
            '--is-hidden'          => 'Whether the user is hidden from the UI',
          ]
        ],
        'users:generate:superadmin' => [
          'usage' => 'iam:users:generate:superadmin',
          'desc'  => 'Generate a new superadmin user.',
        ],
        'users:remove' => [
          'usage' => 'iam:users:remove',
          'desc'  => 'Delete a user by its ID.',
        ],
        'users:set:access-profiles' => [
          'usage' => 'iam:users:set:access-profiles [--remove]',
          'desc'  => 'Add or remove access profiles for a user.',
          'flags' => [
            '--remove'      => 'Whether to remove the access profiles instead of adding them',
          ]
        ],
        'users:help'             => [
          'usage' => 'iam:users:help',
          'desc'  => 'Show this help screen.',
        ],
      ];

      // 2) Summary table
      Utils::printLn($helper->ansi("\nAvailable commands:\n", 'color: cyan; text-decoration: underline'));

      $rows = [
        [
          'cmd'  => 'iam:users:list',
          'desc' => 'Page through existing users',
          'opts' => '--limit, --sort-by, --sort-direction, --page',
        ],
        [
          'cmd'  => 'iam:users:create',
          'desc' => 'Interactively create a new user',
          'opts' => '--is-superadmin, --is-hidden',
        ],
        [
          'cmd'  => 'iam:users:generate:superadmin',
          'desc' => 'Generate a new superadmin user',
          'opts' => '(no flags)',
        ],
        [
          'cmd'  => 'iam:users:remove',
          'desc' => 'Delete a user by ID',
          'opts' => '(no flags)',
        ],
        [
          'cmd'  => 'iam:users:set:access-profiles',
          'desc' => 'Add or remove access profiles for a user.',
          'opts' => '--remove',
        ],
      ];

      $helper->table($rows, [
        'cmd'  => 'Command',
        'desc' => 'Description',
        'opts' => 'Options',
      ]);

      // 3) Detailed usage lists
      foreach ($commands as $cmd => $meta) {
        Utils::printLn($helper->ansi("\n{$cmd}", 'color: yellow; font-weight: bold'));
        Utils::printLn("  Usage:   {$meta['usage']}");
        Utils::printLn("  Purpose: {$meta['desc']}");

        if (!empty($meta['flags'])) {
          Utils::printLn("  Options:");
          $flagLines = [];
          foreach ($meta['flags'] as $flag => $explain) {
            $flagLines[] = "{$flag}  — {$explain}";
          }
          $helper->listItems($flagLines, false, '    •');
        }
      }

      Utils::printLn(''); // trailing newline
    });
  }

  private function setUserAvatar(&$user)
  {
    $setAvatar = readline("  >> Would you like to set an avatar for this user? (y/n): ");

    if (strtolower($setAvatar) === 'y') {
      $userAvatarUrl = readline("    -> Enter the file URL for the avatar: ");

      if (is_file($userAvatarUrl)) {
        $userAvatarUrl = realpath($userAvatarUrl);
      } elseif (!filter_var($userAvatarUrl, FILTER_VALIDATE_URL)) {
        Utils::printLn("    >> Invalid URL or file path provided for avatar.");
        $this->setUserAvatar($user); // Retry setting avatar
        return;
      }

      $user->user_avatar = [
        'name' => basename($userAvatarUrl),
        'path'  => $userAvatarUrl,
      ];
    }
  }
}
