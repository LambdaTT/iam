<?php

namespace Iam\Commands;

use SplitPHP\Cli;
use SplitPHP\Utils;

class Accessprofiles extends Cli
{
  public function init(): void
  {
    $this->addCommand('list', function ($args) {
      $getRows = function ($params) {
        return $this->getService('iam/accessprofile')->list($params);
      };

      $columns = [
        'id_iam_accessprofile' => 'ID',
        'ds_title'             => 'Title',
        'tx_description'       => 'Description',
        'ds_tag'               => 'Tag',
      ];

      $this->getService('utils/misc')->printDataTable("Access Profiles List", $getRows, $columns, $args);
    });

    $this->addCommand('create', function () {
      Utils::printLn("Welcome to the Iam Access Profile Create Command!");
      Utils::printLn("This command will help you create a new access profile in the IAM system.");
      Utils::printLn();
      Utils::printLn(" >> Please follow the prompts to define your access profile informations.");
      Utils::printLn();
      Utils::printLn("  >> New Access Profile:");
      Utils::printLn("------------------------------------------------------");

      $profile = $this->getService('utils/clihelper')->inputForm([
        'ds_title' => [
          'label' => 'Title',
          'required' => true,
          'length' => 60,
        ],
        'tx_description' => [
          'label' => 'Description',
        ],
        'ds_tag' => [
          'label' => 'Tag',
          'required' => false,
          'length' => 10,
        ],
      ]);

      $record = $this->getService('iam/accessprofile')->create($profile);

      Utils::printLn("  >> Iam Access Profile created successfully!");
      foreach ($record as $key => $value) {
        Utils::printLn("    -> {$key}: {$value}");
      }
    });

    $this->addCommand('remove', function () {
      Utils::printLn("Welcome to the Iam Access Profile Removal Command!");
      Utils::printLn();
      $profileId = readline("  >> Please, enter the Access Profile ID you want to remove: ");

      $this->getService('iam/accessprofile')->remove([
        'id_iam_accessprofile' => $profileId,
      ]);
      Utils::printLn("  >> Access Profile with ID {$profileId} removed successfully!");
    });

    $this->addCommand('set:permission', function ($args) {
      Utils::printLn("Welcome to the Iam Access Profile Set Permissions Command!");
      Utils::printLn("This command will help you set permissions for an existing access profile in the IAM system.");
      Utils::printLn();
      Utils::printLn(" >> Please follow the prompts to set your access profile permissions.");
      Utils::printLn();

      $profile = $this->setProfile($args);
      $this->setPermissions($profile, $args);
    });

    // Help command
    $this->addCommand('help', function () {
      /** @var \Utils\Services\CliHelper $helper */
      $helper = $this->getService('utils/clihelper');
      Utils::printLn($helper->ansi(strtoupper("Welcome to the Iam Access Profile Help Center!"), 'color: magenta; font-weight: bold'));

      // 1) Define metadata for each command
      $commands = [
        ':accessprofiles:list'   => [
          'usage' => 'iam:accessprofiles:list [--limit=<n>] [--sort-by=<field>] [--sort-direction=<dir>] [--page=<n>]',
          'desc'  => 'Page through existing access profiles.',
          'flags' => [
            '--limit=<n>'          => 'Items per page (default 10)',
            '--sort-by=<field>'    => 'Field to sort by',
            '--sort-direction=<d>' => 'ASC or DESC (default ASC)',
            '--page=<n>'           => 'Page number (default 1)',
          ],
        ],
        ':accessprofiles:create' => [
          'usage' => 'iam:accessprofiles:create',
          'desc'  => 'Interactively create a new access profile.',
        ],
        ':accessprofiles:remove' => [
          'usage' => 'iam:accessprofiles:remove',
          'desc'  => 'Delete an access profile by its ID.',
        ],
        ':accessprofiles:set:permission' => [
          'usage' => 'iam:accessprofiles:set:permission [--remove]',
          'desc'  => 'Associate a known permission with a an access profile.',
          'flags' => [
            '--profile=<profile_id>'          => 'ID of the access profile',
            '--permission=<permission_tag>'   => 'Tag of a known permission',
            '--remove / -r'                   => 'Remove the permission association instead of adding it.',
          ],
        ],
        ':accessprofiles:help'             => [
          'usage' => 'iam:accessprofiles:help',
          'desc'  => 'Show this help screen.',
        ],
      ];

      // 2) Summary table
      Utils::printLn($helper->ansi("\nAvailable commands:\n", 'color: cyan; text-decoration: underline'));

      $rows = [
        [
          'cmd'  => 'iam:accessprofiles:list',
          'desc' => 'Page through existing access profiles',
          'opts' => '--limit, --sort-by, --sort-direction, --page',
        ],
        [
          'cmd'  => 'iam:accessprofiles:create',
          'desc' => 'Interactively create a new access profile',
          'opts' => '(no flags)',
        ],
        [
          'cmd'  => 'iam:accessprofiles:remove',
          'desc' => 'Delete an access profile by ID',
          'opts' => '(no flags)',
        ],
        [
          'cmd'  => 'iam:accessprofiles:set:permissions',
          'desc' => 'Interactively set the permissions for a given access profile',
          'opts' => '--profile',
        ],
        [
          'cmd'  => 'iam:accessprofiles:help',
          'desc' => 'Show this help screen',
          'opts' => '(no flags)',
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

  private function setProfile(array $args = []): object
  {
    // Extract and normalize our options
    if (isset($args['--profile'])) {
      $profileId = $args['--profile'];
    } else {
      Utils::printLn("  >> Please, enter the Profile ID: ");
      $profileId = $this->getService('utils/misc')->persistentCliInput(
        fn($v) => !empty($v) && is_string($v),
        "Profile ID must be an integer and cannot be empty or zero. Please try again:"
      );
    }

    $profile = $this->getService('iam/accessprofile')->get(['id_iam_accessprofile' => $profileId]);
    if (empty($profile)) {
      Utils::printLn("  >> Access Profile with ID '{$profileId}' does not exist. Please try again.");
      return $this->setProfile($args);
    }

    return $profile;
  }

  private function setPermissions(object $profile, array $args = []): void
  {
    $remove = isset($args['--remove']) || isset($args['-r']);

    // Extract and normalize our options
    if (isset($args['--permission'])) {
      $permissionTag = $args['--permission'];
    } else {
      Utils::printLn("  >> Please, enter the Permission tag: ");
      $permissionTag = $this->getService('utils/misc')->persistentCliInput(
        fn($v) => !empty($v) && is_string($v),
        "Permission tag must be a non-empty string. Please try again:"
      );
    }

    $permission = $this->getService('iam/permission')->get(['ds_tag' => $permissionTag]);
    if (empty($permission)) {
      Utils::printLn("  >> There's no permission marked with the tag '{$permissionTag}'. Please try again.");
      $this->setPermissions($profile, $args);
      return;
    }

    if($remove)
      $this->getService('iam/permission')->disassociate($profile->ds_key, $permissionTag);
    else
      $this->getService('iam/permission')->associate($profile->ds_key, $permissionTag);
  }
}
// EOF