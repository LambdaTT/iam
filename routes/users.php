<?php
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//                                                                                                                                                                //
// IAM Preset for DynamoPHP                                                                                                                                       //
//                                                                                                                                                                //
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

use SplitPHP\Database\Dao;
use SplitPHP\WebService;
use SplitPHP\Exceptions\NotFound;
use SplitPHP\Exceptions\BadRequest;
use SplitPHP\Request;

class Users extends WebService
{
  private const USER_PERMISSIONS = [
    'read'    => 'iam.users.read',
    'manage'  => 'iam.users.manage',
  ];

  public function init(): void
  {
    // USER ENDPOINTS:
    /*---------------*/
    $this->addEndpoint('GET', '/v1/user/?userKey?', function ($userKey) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate()) return $this->response->withStatus(401);

      // Validate user permissions:
      $this->getService('iam/permission')->requireAny(self::USER_PERMISSIONS);

      $params = [
        'ds_key' => $userKey,
        'do_hidden' => 'N'
      ];

      $data = $this->getService('iam/user')->get($params);
      if (empty($data)) return $this->response->withStatus(404);

      unset($data->ds_password);

      return $this->response->withData($data);
    });

    $this->addEndpoint('GET', '/v1/user', function ($params) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate())
        return $this->response->withStatus(401);

      // Validate user permissions:
      $this->getService('iam/permission')->requireAny(self::USER_PERMISSIONS);

      return $this->response->withData($this->getService('iam/user')->list($params));
    });

    $this->addEndpoint('POST', '/v1/user', function ($data) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate())
        return $this->response->withStatus(401);

      // Validate user permissions:
      $this->getService('iam/permission')->requireAll(self::USER_PERMISSIONS['manage']);

      $newUser = $this->getService('iam/user')->create($data);
      unset($newUser->ds_password);

      return $this->response
        ->withStatus(201)
        ->withData($newUser);
    });

    $this->addEndpoint('PUT', '/v1/user/?userKey?', function ($userKey, Request $r) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate())
        return $this->response->withStatus(401);

      // Validate user permissions:
      $this->getService('iam/permission')->requireAll(self::USER_PERMISSIONS['manage']);

      $params = [
        'ds_key' => $userKey,
      ];

      $data = $r->getBody();

      $rows = $this->getService('iam/user')->updUser($params, $data);
      if ($rows < 1) return $this->response->withStatus(404);

      return $this->response->withStatus(204);
    });

    $this->addEndpoint('PUT', '/v1/admin-change-pass/?param?', function ($param, Request $r) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate())
        return $this->response->withStatus(401);

      // Validate user permissions:
      $this->getService('iam/permission')->requireAll(self::USER_PERMISSIONS['manage']);

      $data = $r->getBody();
      if (empty($data['ds_password']))
        throw new BadRequest('Forneça a nova senha.');

      $user = $this->getService('iam/user')
        ->get([
          'ds_key' => "\$or|{$param}",
          'ds_username' => "\$or|{$param}",
          'ds_email' => "\$or|{$param}",
          'ds_phone1' => "\$or|{$param}",
          'ds_phone2' => "\$or|{$param}"
        ]);

      if (empty($user))
        throw new NotFound('O usuário não foi encontrado.');

      $data = [
        'ds_password' => $data['ds_password']
      ];
      $rows = $this->getService('iam/user')->updUser(
        [
          'ds_key' => $user->ds_key
        ],
        $data
      );

      if ($rows < 1) return $this->response->withStatus(404);

      return $this->response->withStatus(204);
    });

    $this->addEndpoint('DELETE', '/v1/user/?userKey?', function ($userKey) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate())
        return $this->response->withStatus(401);

      // Validate user permissions:
      $this->getService('iam/permission')->requireAll(self::USER_PERMISSIONS['manage']);

      $params = [
        'ds_key' => $userKey
      ];

      $result = $this->getService('iam/user')->remove($params);
      if ($result < 1) return $this->response->withStatus(404);

      return $this->response->withStatus(204);
    });


    // PASSWORD RESET ENDPOINTS:
    /*---------------*/
    $this->addEndpoint('POST', '/v1/request-password-reset', function (Request $r) {
      $email = $r->getBody('ds_email');
      $data = $this->getService('iam/user')->requestPasswordReset($email);

      return $this->response
        ->withStatus(201)
        ->withData($data);
    }, false);

    $this->addEndpoint('PUT', '/v1/change-password/?token?', function ($token, Request $r) {
      $data = $r->getBody();

      // Auth user login:
      if (!$this->getService('iam/authtoken')->authenticate($token))
        return $this->response->withStatus(401);

      $user = $this->getService('iam/authtoken')->getTokenUser($token);
      if (empty($user)) throw new NotFound("O usuário não foi encontrado.");

      $this->getService('iam/authtoken')->consume($token);
      $data = [
        'ds_password' => $data['ds_password']
      ];

      $rows = $this->getService('iam/user')->updUser(['ds_key' => $user->ds_key], $data);
      if ($rows < 1) return $this->response->withStatus(404);

      return $this->response->withStatus(204);
    }, false);


    // MY ACCOUNT ENDPOINTS:
    /*---------------*/
    $this->addEndpoint('GET', '/v1/my-account', function () {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate())
        return $this->response->withStatus(401);

      $data = $this->getService('iam/session')->getLoggedUser();
      if (empty($data)) return $this->response->withStatus(404);

      unset($data->ds_password);

      return $this->response->withData($data);
    });

    $this->addEndpoint('PUT', '/v1/my-account', function ($data) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate())
        return $this->response->withStatus(401);

      $userKey = $this->getService('iam/session')->getLoggedUser()->ds_key;

      $rows = $this->getService('iam/user')->updUser(['ds_key' => $userKey], $data);
      if ($rows < 1) return $this->response->withStatus(404);

      return $this->response->withStatus(204);
    });


    // PROFILES ENDPOINTS:
    /*---------------*/
    $this->addEndpoint('GET', '/v1/profile/?userKey?', function ($userKey) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate())
        return $this->response->withStatus(401);

      // Validate Permissions:
      $this->getService('iam/permission')->requireAny(self::USER_PERMISSIONS);

      $usrProfiles = $this->getService('iam/user')->listProfiles([
        'ds_key' => $userKey
      ]);

      return $this->response
        ->withStatus(200)
        ->withData($usrProfiles);
    });

    $this->addEndpoint('PUT', '/v1/profile/?userKey?', function ($userKey, Request $r) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate())
        return $this->response->withStatus(401);

      // Validate Permissions:
      $this->getService('iam/permission')->requireAll(self::USER_PERMISSIONS['manage']);

      $usrFilter = ['ds_key' => $userKey];
      $profileTags = $r->getBody('profile_tags');
      
      $this->getService('iam/user')->clearUserProfiles($usrFilter);
      Dao::flush();
      $code = match ($this->getService('iam/user')->attachProfiles($usrFilter, $profileTags) > 0) {
        true => 204,
        false => 404
      };

      return $this->response->withStatus($code);
    });
  }
}
