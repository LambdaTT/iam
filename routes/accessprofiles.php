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

namespace Iam\Routes;

use SplitPHP\WebService;
use Exception;

class Accessprofiles extends WebService
{
  public function init()
  {
    // PROFILE ENDPOINTS:
    $this->addEndpoint('GET', '/v1/accessprofile/?profileKey?', function ($params) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate()) return $this->response->withStatus(401);

      // Validate user permissions:
      $this->getService('iam/permission')->validatePermissions([
        'IAM_ACCESSPROFILE' => 'R'
      ]);

      $data = $this->getService('iam/accessprofile')->get(['ds_key' => $params['profileKey']]);
      if (empty($data)) return $this->response->withStatus(404);

      return $this->response->withData($data);
    });

    $this->addEndpoint('GET', '/v1/accessprofile', function ($params) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate()) return $this->response->withStatus(401);

      // Validate user permissions:
      $this->getService('iam/permission')->validatePermissions([
        'IAM_ACCESSPROFILE' => 'R'
      ]);

      return $this->response->withData($this->getService('iam/accessprofile')->list($params));
    });
    
    $this->addEndpoint('POST', '/v1/accessprofile', function($data){
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate()) return $this->response->withStatus(401);
  
      // Validate user permissions:
      $this->getService('iam/permission')->validatePermissions([
        'IAM_ACCESSPROFILE' => 'C'
      ]);
  
      return $this->response->withStatus(201)->withData($this->getService('iam/accessprofile')->create($data));
    });

    $this->addEndpoint('PUT', '/v1/accessprofile/?profileKey?', function($params){
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate()) return $this->response->withStatus(401);
  
      // Validate user permissions:
      $this->getService('iam/permission')->validatePermissions([
        'IAM_ACCESSPROFILE' => 'U'
      ]);
  
      $profileKey = $params['profileKey'];
      unset($params['profileKey']);
  
      $rows = $this->getService('iam/accessprofile')->updProfile(['ds_key' => $profileKey], $params);
      if ($rows < 1) return $this->response->withStatus(404);
  
      return $this->response->withStatus(204);
    });

    $this->addEndpoint('DELETE', '/v1/accessprofile/?profileKey?', function($params){
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate()) return $this->response->withStatus(401);
  
      // Validate user permissions:
      $this->getService('iam/permission')->validatePermissions([
        'IAM_ACCESSPROFILE' => 'D'
      ]);
  
      $result = $this->getService('iam/accessprofile')->remove(['ds_key' => $params['profileKey']]);
      if ($result < 1) return $this->response->withStatus(404);
  
      return $this->response->withStatus(204);
    });

    // MODULE ENDPOINTS:
    $this->addEndpoint('GET', '/v1/module/?profileKey?', function($params){
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate()) return $this->response->withStatus(401);
  
      // Validate user permissions:
      $this->getService('iam/permission')->validatePermissions([
        'IAM_ACCESSPROFILE_MODULE' => 'R',
        'IAM_ACCESSPROFILE' => 'R'
      ]);
  
      $profileKey = $params['profileKey'];
      unset($params['profileKey']);
  
      return $this->response->withData($this->getService('iam/accessprofile')->profileModules($profileKey, $params));
    });

    $this->addEndpoint('POST', '/v1/module/?profileKey?/?moduleKey?', function($params){
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate()) return $this->response->withStatus(401);
  
      // Validate user permissions:
      $this->getService('iam/permission')->validatePermissions([
        'IAM_ACCESSPROFILE_MODULE' => 'C',
        'IAM_ACCESSPROFILE_PERMISSION' => 'C',
        'IAM_ACCESSPROFILE' => 'R'
      ]);
  
      $profile = $this->getService('iam/accessprofile')->get(['ds_key' => $params['profileKey']]);
      $module = $this->getService('modcontrol/control')->get(['ds_key' => $params['moduleKey']]);
  
      if (empty($module) || empty($profile)) throw new Exception("Invalid params", 400);
  
      $data = $this->getService('iam/accessprofile')->addModule($profile->id_iam_accessprofile, $module->id_apm_module);
      return $this->response->withStatus(201)->withData($data);
    });

    $this->addEndpoint('DELETE', '/v1/module/?profileKey?/?moduleKey?', function($params){
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate()) return $this->response->withStatus(401);
  
      // Validate user permissions:
      $this->getService('iam/permission')->validatePermissions([
        'IAM_ACCESSPROFILE_MODULE' => 'D',
        'IAM_ACCESSPROFILE' => 'R'
      ]);
  
      $profile = $this->getService('iam/accessprofile')->get(['ds_key' => $params['profileKey']]);
      $module = $this->getService('modcontrol/control')->get(['ds_key' => $params['moduleKey']]);
  
      if (empty($module) || empty($profile)) throw new Exception("Invalid params", 400);
  
      $affectedRows = $this->getService('iam/accessprofile')->removeModule([
        'id_iam_accessprofile' => $profile->id_iam_accessprofile,
        'id_apm_module' => $module->id_apm_module
      ]);
  
      if ($affectedRows < 1) return $this->response->withStatus(404);
  
      return $this->response->withStatus(204);
    });

    // PERMISSION ENDPOINTS:
    $this->addEndpoint('GET', '/v1/permission/?profileKey?', function($params){
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate()) return $this->response->withStatus(401);
  
      // Validate user permissions:
      $this->getService('iam/permission')->validatePermissions([
        'IAM_ACCESSPROFILE_PERMISSION' => 'R',
        'IAM_ACCESSPROFILE_MODULE' => 'R',
        'IAM_ACCESSPROFILE' => 'R',
        'IAM_CUSTOM_PERMISSION' => 'R',
        'IAM_ACCESSPROFILE_CUSTOM_PERMISSION' => 'R'
      ]);
  
      $profileKey = $params['profileKey'];
      unset($params['profileKey']);
  
      $data = $this->getService('iam/permission')->permissionsByModule($profileKey, $params);
  
      return $this->response->withData($data);
    });

    $this->addEndpoint('PUT', '/v1/permission/?profileKey?', function($data){
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate()) return $this->response->withStatus(401);
  
      // Validate user permissions:
      $this->getService('iam/permission')->validatePermissions([
        'IAM_ACCESSPROFILE_PERMISSION' => 'U',
        'IAM_CUSTOM_PERMISSION' => 'R',
        'IAM_ACCESSPROFILE' => 'R',
        'IAM_ACCESSPROFILE_CUSTOM_PERMISSION' => 'CD'
      ]);
  
      foreach ($data['entityPermissions'] as $perm) {
        $this->getService('iam/permission')->updPermission(['ds_key' => $perm['permission_key']], [
          'do_read' => $perm['do_read'],
          'do_create' => $perm['do_create'],
          'do_update' => $perm['do_update'],
          'do_delete' => $perm['do_delete']
        ]);
      }
  
      foreach ($data['customPermissions'] as $cperm) {
        if ($cperm['do_execute'] == 'Y')
          $this->getService('iam/permission')->relateCustomPermission($data['profileKey'], $cperm['permission_key']);
        elseif ($cperm['do_execute'] == 'N')
          $this->getService('iam/permission')->customPermissionRemoveRelation($data['profileKey'], $cperm['permission_key']);
      }
  
      return $this->response->withStatus(204);
    });
    
    $this->addEndpoint('POST', '/v1/permission', function($data){
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate()) return $this->response->withStatus(401);
  
      // Validate user permissions:
      $this->getService('iam/permission')->validatePermissions([
        'IAM_CUSTOM_PERMISSION' => 'C'
      ]);
  
      $result = $this->getService('iam/permission')->createExecPermission($data);
  
      return $this->response->withStatus(201)->withData($result);
    });
  }
}
