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
use Exception;
use SplitPHP\Exceptions\NotFound;

class Accessprofile extends Service
{
  // List all profiles, based on parameters.
  public function list($params = [])
  {
    return $this->getDao('IAM_ACCESSPROFILE')
      ->bindParams($params)
      ->find(
        "SELECT 
          id_iam_accessprofile, 
          ds_title, 
          ds_tag, 
          dt_created, 
          ds_key 
        FROM `IAM_ACCESSPROFILE` "
      );
  }

  // Get, based on parameters, a single access profile on the database. If no profile were found, return null. 
  public function get($params = [])
  {
    return $this->getDao('IAM_ACCESSPROFILE')
      ->bindParams($params)
      ->first(
        "SELECT 
            prf.*, 
            CONCAT(usrc.ds_first_name, ' ', usrc.ds_last_name) as userCreated, 
            DATE_FORMAT(prf.dt_created, '%d/%m/%Y %T') as dtCreated,  
            CONCAT(usru.ds_first_name, ' ', usru.ds_last_name) as userUpdated, 
            DATE_FORMAT(prf.dt_updated, '%d/%m/%Y %T') as dtUpdated 
            FROM `IAM_ACCESSPROFILE` prf 
            LEFT JOIN `IAM_USER` usrc ON (usrc.id_iam_user = prf.id_iam_user_created) 
            LEFT JOIN `IAM_USER` usru ON (usru.id_iam_user = prf.id_iam_user_updated)"
      );
  }

  // Create a new access profile in the database, then returns its new register.
  public function create($data)
  {
    // Removes forbidden fields from $data:
    $data = $this->getService('utils/misc')->dataBlacklist($data, [
      'id_iam_accessprofile',
      'ds_key',
      'do_active',
      'id_iam_user_created',
      'id_iam_user_updated',
      'dt_created',
      'dt_updated'
    ]);

    // Set default values:
    $data['ds_key'] = uniqid();
    $loggedUser = $this->getService('iam/session')->getLoggedUser();
    $data['id_iam_user_created'] = $loggedUser ? $loggedUser->id_iam_user : null;

    return $this->getDao('IAM_ACCESSPROFILE')->insert($data);
  }

  // Update access profiles in the database, based on parameters.
  public function upd($params, $data)
  {
    // Removes forbidden fields from $data:
    $data = $this->getService('utils/misc')->dataBlacklist($data, [
      'id_iam_accessprofile',
      'ds_key',
      'id_iam_user_created',
      'id_iam_user_updated',
      'dt_created',
      'do_active',
      'dt_updated'
    ]);

    // Set default values:
    $data['id_iam_user_updated'] = $this->getService('iam/session')->getLoggedUser()->id_iam_user;
    $data['dt_updated'] = date('Y-m-d H:i:s');

    // Perform Access Profile update:
    return $this->getDao('IAM_ACCESSPROFILE')
      ->bindParams($params)
      ->update($data);
  }

  // Delete access profiles in the database, based on parameters.
  public function remove($params)
  {
    return $this->getDao('IAM_ACCESSPROFILE')
      ->bindParams($params)
      ->delete();
  }

  // List all modules related to a profile, based on parameters. */
  public function getModules($profileKey, $params = [])
  {
    return $this->getDao('MDC_MODULE')
      ->filter('profileKey')->equalsTo($profileKey)
      ->bindParams($params)
      ->find('iam/profilemodules');
  }

  /**
   * Update the modules associated with a profile.
   * @param array $prfParams Parameters to find the profile.
   * @param array $modules Array of modules to update, each with 'id_mdc_module' and 'checked' keys.
   * @throws NotFound If the profile is not found
   */
  public function updProfileModules($prfParams, $modules)
  {
    $prf = $this->get($prfParams);
    if (empty($prf)) throw new NotFound("O perfil não foi encontrado.");

    foreach ($modules as $mod) {
      $mod = (array) $mod;
      if ($mod['checked'] == 'Y')
        $this->addModule($prf->id_iam_accessprofile, $mod['id_mdc_module']);
      else
        $this->removeModule([
          'id_iam_accessprofile' => $prf->id_iam_accessprofile,
          'id_mdc_module' => $mod['id_mdc_module']
        ]);
    }
  }

  // Associate a module to a profile
  public function addModule($profileId, $moduleId)
  {
    $conflict = $this->getDao('IAM_ACCESSPROFILE_MODULE')
      ->filter('id_mdc_module')->equalsTo($moduleId)
      ->filter('id_iam_accessprofile')->equalsTo($profileId)
      ->first("SELECT id_iam_accessprofile_module FROM `IAM_ACCESSPROFILE_MODULE` WHERE id_mdc_module = ?id_mdc_module? AND id_iam_accessprofile = ?id_iam_accessprofile?");
    if (!empty($conflict)) return;

    // Associates a module to a profile
    $association = $this->getDao('IAM_ACCESSPROFILE_MODULE')
      ->insert([
        'id_mdc_module' => $moduleId,
        'id_iam_accessprofile' => $profileId
      ]);

    // Generates permissions to each entity within the module for the profile
    $entities = $this->getDao('MDC_MODULE_ENTITY')
      ->filter('id_mdc_module')->equalsTo($moduleId)
      ->find("SELECT id_mdc_module_entity FROM `MDC_MODULE_ENTITY` WHERE id_mdc_module = ?id_mdc_module?");

    foreach ($entities as $ent) {
      $this->getDao('IAM_ACCESSPROFILE_PERMISSION')
        ->insert([
          'ds_key' => uniqid(),
          'id_iam_accessprofile_module' => $association->id_iam_accessprofile_module,
          'id_mdc_module_entity' => $ent->id_mdc_module_entity
        ]);
    }

    return $association;
  }

  // Disassociate a module from a profile
  public function removeModule($params)
  {
    if (empty($params)) throw new Exception("You can't remove modules from profiles without providing params.");

    return $this->getDao('IAM_ACCESSPROFILE_MODULE')
      ->bindParams($params)
      ->delete();
  }

  /**
   * Update the CRUD permission flags for a specific entity within a profile.
   * Looks up the existing IAM_ACCESSPROFILE_PERMISSION record for the given
   * profile + entity and sets do_read/do_create/do_update/do_delete according
   * to the $operations string (e.g. "RU" → read & update = Y, create & delete = N).
   *
   * @param int    $profileId   id_iam_accessprofile
   * @param int    $entityId    id_mdc_module_entity
   * @param string $operations  Any combination of C, R, U, D
   * @return mixed The updated record, or null if the permission row was not found.
   */
  public function applyEntityPermissions(int $profileId, int $entityId, string $operations)
  {
    // Find the association record for this profile+entity:
    $perm = $this->getDao('IAM_ACCESSPROFILE_PERMISSION')
      ->filter('profile_id')->equalsTo($profileId)
      ->and('entity_id')->equalsTo($entityId)
      ->first(
        "SELECT perm.id_iam_accessprofile_permission
           FROM `IAM_ACCESSPROFILE_PERMISSION` perm
           JOIN `IAM_ACCESSPROFILE_MODULE` apm ON apm.id_iam_accessprofile_module = perm.id_iam_accessprofile_module
           WHERE apm.id_iam_accessprofile = ?profile_id?
             AND perm.id_mdc_module_entity = ?entity_id?"
      );

    if (empty($perm)) return null;

    $flags = [
      'do_read'   => str_contains(strtoupper($operations), 'R') ? 'Y' : 'N',
      'do_create' => str_contains(strtoupper($operations), 'C') ? 'Y' : 'N',
      'do_update' => str_contains(strtoupper($operations), 'U') ? 'Y' : 'N',
      'do_delete' => str_contains(strtoupper($operations), 'D') ? 'Y' : 'N',
    ];

    return $this->getDao('IAM_ACCESSPROFILE_PERMISSION')
      ->filter('id_iam_accessprofile_permission')->equalsTo($perm->id_iam_accessprofile_permission)
      ->update($flags);
  }

  public function addProfileToUser($prfParams, $usrParams)
  {
    $prf = $this->get($prfParams);
    if (empty($prf)) throw new NotFound("O perfil não foi encontrado.");

    $usr = $this->getService('iam/user')->get($usrParams);
    if (empty($usr)) throw new NotFound("O usuário não foi encontrado.");

    $association = $this->getDao('IAM_ACCESSPROFILE_USER')
      ->filter('id_iam_user')->equalsTo($usr->id_iam_user)
      ->filter('id_iam_accessprofile')->equalsTo($prf->id_iam_accessprofile)
      ->first("SELECT id_iam_accessprofile_user FROM `IAM_ACCESSPROFILE_USER` WHERE id_iam_user = ?id_iam_user? AND id_iam_accessprofile = ?id_iam_accessprofile?");
    if (!empty($association)) return $association;

    return $this->getDao('IAM_ACCESSPROFILE_USER')
      ->insert([
        'id_iam_user' => $usr->id_iam_user,
        'id_iam_accessprofile' => $prf->id_iam_accessprofile
      ]);
  }

  public function removeProfileFromUser($prfParams, $usrParams)
  {
    $prf = $this->get($prfParams);
    if (empty($prf)) throw new NotFound("O perfil não foi encontrado.");

    $usr = $this->getService('iam/user')->get($usrParams);
    if (empty($usr)) throw new NotFound("O usuário não foi encontrado.");

    return $this->getDao('IAM_USER_ACCESSPROFILE')
      ->filter('id_iam_user')->equalsTo($usr->id_iam_user)
      ->filter('id_iam_accessprofile')->equalsTo($prf->id_iam_accessprofile)
      ->delete();
  }
}
