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

  // Associate a module to a profile
  public function addModule($profileId, $moduleId)
  {
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
}
