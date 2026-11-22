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

use SplitPHP\Request;
use SplitPHP\WebService;

class Accessprofiles extends WebService
{
  private const ACCESSPROFILE_PERMISSIONS = [
    'read'    => 'iam.accessprofiles.read',
    'manage'  => 'iam.accessprofiles.manage',
  ];

  public function init()
  {
    // PROFILE ENDPOINTS:
    /*---------------*/
    $this->addEndpoint('GET', '/v1/profile/?profileKey?', function ($profileKey) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate())
        return $this->response->withStatus(401);

      // Validate user permissions:
      $this->getService('iam/permission')->requireAny(self::ACCESSPROFILE_PERMISSIONS);

      $data = $this->getService('iam/accessprofile')->get(['ds_key' => $profileKey]);
      if (empty($data)) return $this->response->withStatus(404);

      return $this->response->withData($data);
    });

    $this->addEndpoint('GET', '/v1/profile', function ($params) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate())
        return $this->response->withStatus(401);

      // Validate user permissions:
      $this->getService('iam/permission')->requireAny(self::ACCESSPROFILE_PERMISSIONS);

      return $this->response->withData($this->getService('iam/accessprofile')->list($params));
    });

    $this->addEndpoint('POST', '/v1/profile', function (Request $r) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate())
        return $this->response->withStatus(401);

      // Validate user permissions:
      $this->getService('iam/permission')->requireAll(self::ACCESSPROFILE_PERMISSIONS['manage']);

      $data = $r->getBody();

      return $this->response->withStatus(201)->withData($this->getService('iam/accessprofile')->create($data));
    });

    $this->addEndpoint('PUT', '/v1/profile/?profileKey?', function ($profileKey, Request $r) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate())
        return $this->response->withStatus(401);

      // Validate user permissions:
      $this->getService('iam/permission')->requireAll(self::ACCESSPROFILE_PERMISSIONS['manage']);

      $params = [
        'ds_key' => $profileKey
      ];

      $data = $r->getBody();

      $rows = $this->getService('iam/accessprofile')->upd($params, $data);
      if ($rows < 1) return $this->response->withStatus(404);

      return $this->response->withStatus(204);
    });

    $this->addEndpoint('DELETE', '/v1/profile/?profileKey?', function ($profileKey) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate()) return $this->response->withStatus(401);

      // Validate user permissions:
      $this->getService('iam/permission')->requireAll(self::ACCESSPROFILE_PERMISSIONS['manage']);

      $params = [
        'ds_key' => $profileKey
      ];

      $result = $this->getService('iam/accessprofile')->remove($params);
      if ($result < 1) return $this->response->withStatus(404);

      return $this->response->withStatus(204);
    });


    // PERMISSIONS ENDPOINTS:
    /*---------------*/
    $this->addEndpoint('GET', '/v1/permission/?profileKey?', function ($profileKey) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate())
        return $this->response->withStatus(401);

      // Validate user permissions:
      $this->getService('iam/permission')->requireAny(self::ACCESSPROFILE_PERMISSIONS);

      $params = [
        'ds_key' => $profileKey
      ];

      $data = $this->getService('iam/accessprofile')->listPermissions($params);

      return $this->response->withData($data);
    });

    $this->addEndpoint('PUT', '/v1/permission/?profileKey?', function ($profileKey, Request $r) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate())
        return $this->response->withStatus(401);

      // Validate Permissions:
      $this->getService('iam/permission')->requireAll(self::ACCESSPROFILE_PERMISSIONS['manage']);

      $prfFilter = ['ds_key' => $profileKey];
      $this->getService('iam/profile')->clearPermissions($prfFilter);
      $code = match ($this->getService('iam/profile')->attachPermissions($prfFilter, $r->getBody('permission_tags')) > 0) {
        true => 204,
        false => 404
      };

      return $this->response->withStatus($code);
    });
  }
}
