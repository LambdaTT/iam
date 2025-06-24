<?php

namespace Iam\Commands;

use SplitPHP\Cli;
use SplitPHP\Utils;

class Set extends Cli
{
  public function init()
  {
    $this->addCommand('user:superadmin', function () {
      Utils::printLn("  >> Inserting superadmin user...");
      $sa = $this->getDao('IAM_USER')
        ->insert([
          `ds_key` => 'usr-' . uniqid(),
          `ds_email` => 'system@admin.com',
          `ds_password` => password_hash('Pass123', PASSWORD_DEFAULT),
          `ds_first_name` => 'Super',
          `ds_last_name` => 'Admin',
          `ds_company` => null,
          `id_fmn_file_avatar` => null,
          `dt_last_access` =>  null,
          `do_active` => 'Y',
          `do_session_expires` => 'N',
          `do_is_superadmin` => 'Y',
          `do_hidden` => 'Y',
        ]);

      if ($sa) {
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
      } else {
        Utils::printLn("  >> Failed to insert superadmin user.");
      }
    });

    $this->addCommand('accessprofile', function () {
      $name = readline("    -> Enter the name for the new access profile: ");
      $description = readline("    -> Enter a description for the new access profile: ");

      Utils::printLn("  >> Inserting new access profile...");
      $profile = $this->getDao('IAM_ACCESSPROFILE')
        ->insert([
          'ds_key' => 'prf-' . uniqid(),
          'ds_title' => $name,
          'tx_description' => $description,
          'ds_tag' => 'admin'
        ]);

      $avMods = $this->getService('modcontrol/control')->list();
      foreach ($avMods as $mod) {
        Utils::printLn("  >> Adding module on '{$name}'...");
        Utils::printLn("  -> Do you want to add the module '{$mod->ds_title}' to the access profile? (y/n): ");
        $confirm = strtolower(readline("    -> Type your choice: "));
        if (!$confirm || $confirm == 'n') {
          continue;
        }

        Utils::printLn("  >> Setting module '{$mod->ds_title}' to profile '{$name}'...");
        $prfMods = $this->getDao('IAM_ACCESSPROFILE_MODULE')
          ->insert([
            'id_mdc_module' => $mod->id_mdc_module,
            'id_iam_accessprofile' => $profile->id_iam_accessprofile
          ]);
        Utils::printLn("  >> Module '{$mod->ds_title}' set to profile '{$name}' successfully.");
        Utils::printLn();

        Utils::printLn("  >> Setting module permissions per entity:");
        $entities = $this->getService('modcontrol/control')->getModuleEntities(['id_mdc_module' => $mod->id_mdc_module]);
        foreach ($entities as $ent) {
          $c = strtoupper(readline("    -> Set 'CREATE' permission for entity '{$ent->ds_entity_name}({$ent->ds_entity_label})' in this profile? (y/n): "));
          $r = strtoupper(readline("    -> Set 'READ' permission for entity '{$ent->ds_entity_name}({$ent->ds_entity_label})' in this profile? (y/n): "));
          $u = strtoupper(readline("    -> Set 'UPDATE' permission for entity '{$ent->ds_entity_name}({$ent->ds_entity_label})' in this profile? (y/n): "));
          $d = strtoupper(readline("    -> Set 'DELETE' permission for entity '{$ent->ds_entity_name}({$ent->ds_entity_label})' in this profile? (y/n): "));
          $confirm = strtolower(readline("    -> Entity '{$ent->ds_entity_name}({$ent->ds_entity_label})' - Create='{$c}', Read='{$r}', Update='{$u}', Delete='{$d}'. Confirm permissions? (y/n): "));

          if ($confirm)
            $this->getDao('IAM_ACCESSPROFILE_PERMISSION')
              ->insert([
                'ds_key' => 'prm-' . uniqid(),
                'id_iam_accessprofile_module' => $prfMods->id_iam_accessprofile_module,
                'id_mdc_module_entity' => $ent->id_mdc_module_entity,
                'do_create' => $c,
                'do_read' => $r,
                'do_update' => $u,
                'do_delete' => $d
              ]);


          Utils::printLn("  >> Permissions for entity '{$ent->ds_entity_name}({$ent->ds_entity_label})' set successfully on profile '{$name}'.");
          Utils::printLn();
        }
      }
    });
  }
}
