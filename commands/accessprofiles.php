<?php

namespace Iam\Commands;

use SplitPHP\Cli;
use SplitPHP\Utils;
use stdClass;

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

    $this->addCommand('set:permissions', function () {
      Utils::printLn("Welcome to the Iam Access Profile Set Permissions Command!");
      Utils::printLn("This command will help you set permissions for an existing access profile in the IAM system.");
      Utils::printLn();
      Utils::printLn(" >> Please follow the prompts to set your access profile permissions.");
      Utils::printLn();

      $profile = $this->setProfile();
      $this->setPermissions($profile);
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
        ':accessprofiles:set:permissions' => [
          'usage' => 'iam:accessprofiles:set:permissions',
          'desc'  => 'Interactively set the permissions for a given access profile.',
          'flags' => [
            '--profile=<profile_id>'          => 'ID of the access profile',
            '--module=<module_name>'           => 'Name of the module to set permissions for',
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
          'opts' => '--profile, --module',
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

  private function setProfile(array $args = []): stdClass
  {
    // Extract and normalize our options
    if (isset($args['--profile'])) {
      $profileId = $args['--profile'];
    } else {
      Utils::printLn("  >> Please, enter the Profile ID: ");
      $profileId = $this->getService('utils/misc')->persistentCliInput(
        function ($v) {
          return !empty($v) && is_string($v);
        },
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

  private function setModule(array $args = []): stdClass
  {
    // Extract and normalize our options
    if (isset($args['--module'])) {
      $moduleName = $args['--module'];
    } else {
      Utils::printLn("  >> Please, enter the Module Name: ");
      $moduleName = $this->getService('utils/misc')->persistentCliInput(
        function ($v) {
          return !empty($v) && is_string($v);
        },
        "Module name must be a string and cannot be empty. Please try again:"
      );
    }

    $moduleName = strtolower($moduleName);
    $module = $this->getService('modcontrol/control')->get(['ds_title' => $moduleName]);
    if (empty($module)) {
      Utils::printLn("  >> Module '{$moduleName}' does not exist. Please try again.");
      return $this->setModule($args);
    }

    return $module;
  }

  private function setPermissions($profile)
  {
    while (true) {
      $module = $this->setModule();
      Utils::printLn("  >> Granting access to module '{$module->ds_title}' for profile '{$profile->ds_title}'...");
      $prfMod = $this->getDao('IAM_ACCESSPROFILE_MODULE')
        ->insert([
          'id_iam_accessprofile' => $profile->id_iam_accessprofile,
          'id_mdc_module'        => $module->id_mdc_module,
        ]);
      Utils::printLn("  >> Access to module '{$module->ds_title}' for profile '{$profile->ds_title}' granted successfully.");
      Utils::printLn();

      Utils::printLn("  >> Now, let's set permissions for each entity in the module '{$module->ds_title}'...");
      Utils::printLn("  >> You will be prompted to set CREATE, READ, UPDATE, and DELETE permissions for each entity in the module.");
      $onlyNOrY = function ($input) {
        return in_array(strtoupper($input), ['Y', 'N']);
      };
      $modEntities = $this->getService('modcontrol/control')->getModuleEntities(modParams: ['id_mdc_module' => $module->id_mdc_module]);
      foreach ($modEntities as $entity) {
        Utils::printLn("  >> Setting permissions for module entity: {$entity->ds_entity_name} ({$entity->ds_entity_label}):");
        Utils::printLn("    -> Set the CREATE permission for this entity? (y/n): ");
        $c = strtoupper($this->getService('utils/misc')->persistentCliInput($onlyNOrY, "  >> You can only answer with 'Y' or 'N'. Please try again: "));
        Utils::printLn("    -> Set the READ permission for this entity? (y/n): ");
        $r = strtoupper($this->getService('utils/misc')->persistentCliInput($onlyNOrY, "  >> You can only answer with 'Y' or 'N'. Please try again: "));
        Utils::printLn("    -> Set the UPDATE permission for this entity? (y/n): ");
        $u = strtoupper($this->getService('utils/misc')->persistentCliInput($onlyNOrY, "  >> You can only answer with 'Y' or 'N'. Please try again: "));
        Utils::printLn("    -> Set the DELETE permission for this entity? (y/n): ");
        $d = strtoupper($this->getService('utils/misc')->persistentCliInput($onlyNOrY, "  >> You can only answer with 'Y' or 'N'. Please try again: "));

        $this->getDao('IAM_ACCESSPROFILE_PERMISSION')
          ->insert([
            'ds_key'                      => 'prm-' . uniqid(),
            'id_iam_accessprofile_module' => $prfMod->id_iam_accessprofile_module,
            'id_mdc_module_entity'        => $entity->id_mdc_module_entity,
            'do_create'                   => $c,
            'do_read'                     => $r,
            'do_update'                   => $u,
            'do_delete'                   => $d,
          ]);
        Utils::printLn("  >> Permissions of '{$entity->ds_entity_name}' successfully set for profile '{$profile->ds_title}'.");
        Utils::printLn();
      }

      $repeat = (strtoupper(readline("  >> Would you like to set permissions for another module? (Y/N): ")) == 'Y');
      if (!$repeat) break;
    }

    Utils::printLn("  >> Permissions setup completed for profile '{$profile->ds_title}'.");
  }
}
// EOF