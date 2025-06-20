<?php
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//                                                                                                                                                                //
// IAM Preset for DynamoPHP                                                                                                                                       //
//                                                                                                                                                                //
// IAM is an alias for Identity and Access Manager, which manages user's authentication, permissions, access profiles and teams within an application.            //
// Many apps use this kind of functionality and this is a complete ready-to-work preset, that you can import into your DynamoPHP application.                     //
//                                                                                                                                                                //
// See more info about it at: https://github.com/gabriel-guelfi/IAM                                                                                               //
//                                                                                                                                                                //
// MIT License                                                                                                                                                    //
//                                                                                                                                                                //
// Copyright (c) 2021 Dynamo PHP Community                                                                                                                        //
//                                                                                                                                                                //
// Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to          //
// deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or         //
// sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:                            //
//                                                                                                                                                                //
// The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.                                 //
//                                                                                                                                                                //
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,FITNESS     //
// FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY           //
// WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.     //
//                                                                                                                                                                //
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

namespace Iam\Services;

use SplitPHP\Service;
use SplitPHP\System;
use Exception;

class Permission extends Service
{
  // List all permissions related to all modules that are related to a single access profile, identified by its unique key.
  public function permissionsByModule($profileKey)
  {
    // Get a list of all modules related to the profile:
    $modules = $this->getService('iam/accessprofile')->profileModules($profileKey, ['$sort_by' => 1]);

    // For each module found, retrieve a list of its permissions.
    $results = [];
    foreach ($modules as $mod) {
      if ($mod->checked == 'Y') {
        $mod->permissions = $this->getDao('IAM_ACCESSPROFILE_PERMISSION')
          ->filter('module_id')->equalsTo($mod->id_apm_module)
          ->and('profile_key')->equalsTo($profileKey)
          ->find('iam/permissionsbymodule');

        $results[] = $mod;
      }
    }

    return $results;
  }

  // Create a new custom(execution) permission.
  public function createExecPermission($data)
  {
    // Removes forbidden fields from $data:
    $data = $this->getService('utils/misc')->dataBlacklist($data, [
      'id_iam_custom_permission',
      'id_iam_user_created',
      'dt_created'
    ]);

    // Set default values:
    $data['id_iam_user_created'] = $this->getService('iam/session')->getLoggedUser()->id_iam_user;

    return $this->getDao('IAM_CUSTOM_PERMISSION')->insert($data);
  }

  // Based on parameters, update a permission with the data passed.
  public function updPermission($params, $data)
  {
    // Removes forbidden fields from $data:
    $data = $this->getService('utils/misc')->dataBlacklist($data, [
      'id_iam_accessprofile_permission',
      'ds_key',
      'id_iam_accessprofile_module',
      'id_apm_module_entity'
    ]);

    return $this->getDao('IAM_ACCESSPROFILE_PERMISSION')
      ->bindParams($params)
      ->update($data);
  }

  // Attach a custom(execution) permission to an access profile, both identified by their unique keys.
  public function relateCustomPermission($profileKey, $permissionKey)
  {
    // Create associative dataset which will be inserted on the database:
    $toSave = [
      'id_iam_custom_permission' => $this->getDao('IAM_CUSTOM_PERMISSION')->filter('ds_key')->equalsTo($permissionKey)->find("SELECT id_iam_custom_permission FROM `IAM_CUSTOM_PERMISSION` WHERE ds_key = ?ds_key?")[0]->id_iam_custom_permission,
      'id_iam_accessprofile' => $this->getDao('IAM_ACCESSPROFILE')->filter('ds_key')->equalsTo($profileKey)->find("SELECT id_iam_accessprofile FROM `IAM_ACCESSPROFILE` WHERE ds_key = ?ds_key?")[0]->id_iam_accessprofile,
      'id_iam_user_created' => $this->getService('iam/session')->getLoggedUser()->id_iam_user
    ];

    return $this->getDao('IAM_ACCESSPROFILE_CUSTOM_PERMISSION')->insert($toSave);
  }

  // Detach a custom(execution) permission from an access profile, both identified by their unique keys.
  public function customPermissionRemoveRelation($profileKey, $permissionKey)
  {
    $permissionId = $this->getDao('IAM_CUSTOM_PERMISSION')->filter('ds_key')->equalsTo($permissionKey)->first("SELECT id_iam_custom_permission FROM `IAM_CUSTOM_PERMISSION` WHERE ds_key = ?ds_key?")->id_iam_custom_permission;
    $profileId = $this->getDao('IAM_ACCESSPROFILE')->filter('ds_key')->equalsTo($profileKey)->first("SELECT id_iam_accessprofile FROM `IAM_ACCESSPROFILE` WHERE ds_key = ?ds_key?")->id_iam_accessprofile;

    return $this->getDao('IAM_ACCESSPROFILE_CUSTOM_PERMISSION')
      ->filter('id_iam_custom_permission')->equalsTo($permissionId)
      ->and('id_iam_accessprofile')->equalsTo($profileId)
      ->delete();
  }

  // Validate if the logged user has the specified permission. If the validation succeed, returns true, else returns false or throws an exception.
  public function validatePermissions(array $requiredPermissions, bool $throwException = true)
  {
    $failure = false;
    // If no logged user were found, validation fails:
    $user = $this->getService('iam/session')->getLoggedUser();
    if (empty($user)) {
      if ($throwException) throw new Exception("Você não possui as permissões necessárias para executar esta ação", PERMISSION_DENIED);
      else return false;
    }
    // If logged user were super admin, validation succeeds:
    if ($user->do_is_superadmin == 'Y') return true;
    // List all permissions of the logged user:
    if (empty(System::$globals['userPermissions']))
      System::$globals['userPermissions'] = $this->getDao('IAM_ACCESSPROFILE_PERMISSION')
        ->filter('id_iam_user')->equalsTo($user->id_iam_user)
        ->find("iam/permissionsofuser");
    $userPermissions = System::$globals['userPermissions'];
    // Prepare permission's dataset to perform the checks:
    $processedPermissions = [];
    foreach ($userPermissions as $permission) {
      if (array_key_exists($permission->ds_entity_name, $processedPermissions)) {
        foreach ($processedPermissions[$permission->ds_entity_name] as $lvl => $val)
          if ($val != 'Y') $processedPermissions[$permission->ds_entity_name]->$lvl = $permission->$lvl;
      } else {
        $processedPermissions[$permission->ds_entity_name] = (object) [
          "do_read" => $permission->do_read,
          "do_create" => $permission->do_create,
          "do_update" => $permission->do_update,
          "do_delete" => $permission->do_delete
        ];
      }
    }
    unset($userPermissions);
    // Check each required permission. If the logged user lack the permission or the required level of the permission, validation fails:
    foreach ($requiredPermissions as $ent => $strReq) {
      if (!array_key_exists($ent, $processedPermissions)) {
        $failure = true;
        break;
      }
      if (strpos($strReq, 'C') !== false && $processedPermissions[$ent]->do_create != 'Y') {
        $failure = true;
        break;
      }
      if (strpos($strReq, 'R') !== false && $processedPermissions[$ent]->do_read != 'Y') {
        $failure = true;
        break;
      }
      if (strpos($strReq, 'U') !== false && $processedPermissions[$ent]->do_update != 'Y') {
        $failure = true;
        break;
      }
      if (strpos($strReq, 'D') !== false && $processedPermissions[$ent]->do_delete != 'Y') {
        $failure = true;
        break;
      }
    }
    // If $throwException flag were set to true, on a failed validation, throws an exception, instead of returning false:
    if ($failure) {
      if ($throwException) throw new Exception("Você não possui as permissões necessárias para executar esta ação", PERMISSION_DENIED);
      else return false;
    }
    return true;
  }

  // Validate if the logged user has the specified custom(execution) permission:
  public function canExecute(string $permissionKey, bool $throwException = true)
  {
    // If no logged user were found, validation fails:
    $user = $this->getService('iam/session')->getLoggedUser();
    if (empty($user)) {
      if ($throwException) throw new Exception("Você não possui as permissões necessárias para executar esta ação", PERMISSION_DENIED);
      else return false;
    }
    // If logged user were super admin, validation succeeds:
    if ($user->do_is_superadmin == 'Y') return true;
    // List all custom(execution) permissions of the logged user:
    if (empty(System::$globals['customPermissions']))
      System::$globals['customPermissions'] = $this->getDao('IAM_CUSTOM_PERMISSION')
        ->filter('id_iam_user')->equalsTo($user->id_iam_user)
        ->find('iam/custompermissionsofuser');
    $dbPermissions = System::$globals['customPermissions'];
    // Prepare permission's dataset to perform the checks:
    $permissions = [];
    foreach ($dbPermissions as $dbperm) {
      $permissions[] = $dbperm->ds_key;
    }
    // If the specified custom(execution) permission were not found among the logged user's permissions, validation fails.
    if (!in_array($permissionKey, $permissions)) {
      // If $throwException flag were set to true, on a failed validation, throws an exception, instead of returning false.
      if ($throwException) throw new Exception("Você não possui as permissões necessárias para executar esta ação", PERMISSION_DENIED);
      else return false;
    }
    return true;
  }
  
  // Checks if the logged user has access to at least one of the specified modules.
  public function hasAccessToModules(array $modules)
  {
    // Get the logged user:
    $user = $this->getService('iam/session')->getLoggedUser();
    // If logged user were super admin, validation succeeds:
    if ($user->do_is_superadmin == 'Y') return true;
    // List all modules that the logged user has access to:
    if (empty(System::$globals['userAllowedModules']))
      System::$globals['userAllowedModules'] = $this->getDao('APM_MODULE')
        ->filter('id_iam_user')->equalsTo($user->id_iam_user)
        ->find('iam/usermodules');
    $userAllowedModules = System::$globals['userAllowedModules'];
    // If the user has access of anyone of the specified modules, this validation succeed.
    foreach ($userAllowedModules as $mod) {
      if (in_array($mod->ds_key, $modules)) return true;
    }
    return false;
  }
}
