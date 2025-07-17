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
use SplitPHP\Exceptions\NotFound;
use SplitPHP\Exceptions\BadRequest;
use SplitPHP\Request;

class Users extends WebService
{
  public function init(): void
  {
    // USER ENDPOINTS:
    $this->addEndpoint('GET', '/v1/user/?userKey?', function (Request $r) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate()) return $this->response->withStatus(401);

      // Validate user permissions:
      $this->getService('iam/permission')->validatePermissions([
        'IAM_USER' => 'R'
      ]);

      $params = [
        'ds_key' => $r->getRoute()->params['userKey'],
        'do_hidden' => 'N'
      ];

      $data = $this->getService('iam/user')->get($params);
      if (empty($data)) return $this->response->withStatus(404);

      unset($data->ds_password);

      return $this->response->withData($data);
    });

    $this->addEndpoint('GET', '/v1/user', function ($params) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate()) return $this->response->withStatus(401);

      // Validate user permissions:
      $this->getService('iam/permission')->validatePermissions([
        'IAM_USER' => 'R'
      ]);

      $params['do_hidden'] = 'N';

      return $this->response->withData($this->getService('iam/user')->list($params));
    });

    $this->addEndpoint('POST', '/v1/user', function ($data) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate()) return $this->response->withStatus(401);

      // Validate user permissions:
      $this->getService('iam/permission')->validatePermissions([
        'IAM_USER' => 'C',
        'IAM_ACCESSPROFILE' => 'R',
        'IAM_ACCESSPROFILE_USER' => 'CD'
      ]);

      $profiles = json_decode($data['selected_profiles'], true);
      unset($data['selected_profiles']);

      if (!empty($_FILES['user_avatar'])) {
        $data['user_avatar'] = [
          'name' => $_FILES['user_avatar']['name'],
          'path' => $_FILES['user_avatar']['tmp_name'],
        ];
      }

      $newUser = $this->getService('iam/user')->create($data);
      unset($newUser->ds_password);

      if (!empty($profiles))
        $newUser->profiles = $this->getService('iam/user')->updUserProfiles($newUser->id_iam_user, $profiles);

      return $this->response
        ->withStatus(201)
        ->withData($newUser);
    });

    $this->addEndpoint('PUT', '/v1/user/?userKey?', function (Request $r) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate())
        return $this->response->withStatus(401);

      // Validate user permissions:
      $this->getService('iam/permission')->validatePermissions([
        'IAM_USER' => 'U',
        'IAM_ACCESSPROFILE' => 'R',
        'IAM_ACCESSPROFILE_USER' => 'CD'
      ]);

      $params = [
        'ds_key' => $r->getRoute()->params['userKey'],
      ];

      $data = $r->getBody();

      $user = $this->getService('iam/user')->get($params);
      if (empty($user)) throw new NotFound("O usuário não foi encontrado.");

      $profiles = json_decode($data['selected_profiles'], true);
      unset($data['selected_profiles']);

      $data['do_hidden'] = 'N';
      $rows = $this->getService('iam/user')->updUser($params, $data);
      if ($rows < 1) return $this->response->withStatus(404);

      if (!empty($profiles)) $this->getService('iam/user')->updUserProfiles($user->id_iam_user, $profiles);

      return $this->response->withStatus(204);
    });

    $this->addEndpoint('DELETE', '/v1/user/?userKey?', function (Request $r) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate()) return $this->response->withStatus(401);

      // Validate user permissions:
      $this->getService('iam/permission')->validatePermissions([
        'IAM_USER' => 'D'
      ]);

      $params = [
        'ds_key' => $r->getRoute()->params['userKey'],
        'do_hidden' => 'N'
      ];

      $result = $this->getService('iam/user')->remove($params);
      if ($result < 1) return $this->response->withStatus(404);

      return $this->response->withStatus(204);
    });

    // PASSWORD RESET ENDPOINTS:
    $this->addEndpoint('POST', '/v1/request-password-reset', function (Request $r) {
      $email = $r->getBody('ds_email');
      $data = $this->getService('iam/user')->requestPasswordReset($email);

      return $this->response
        ->withStatus(201)
        ->withData($data);
    }, false);

    $this->addEndpoint('PUT', '/v1/change-password/?authtoken?', function (Request $r) {
      $tkn = $r->getRoute()->params['authtoken'];
      $data = $r->getBody();

      // Auth user login:
      if (!$this->getService('iam/authtoken')->authenticate($tkn))
        return $this->response->withStatus(401);

      $user = $this->getService('iam/authtoken')->getTokenUser($tkn);
      if (empty($user)) throw new NotFound("O usuário não foi encontrado.");

      $this->getService('iam/authtoken')->consume($tkn);
      $data = [
        'ds_password' => $data['ds_password']
      ];

      $rows = $this->getService('iam/user')->updUser(['ds_key' => $user->ds_key], $data);
      if ($rows < 1) return $this->response->withStatus(404);

      return $this->response->withStatus(204);
    }, false);

    $this->addEndpoint('GET', '/redirect/reset-password/?token?', function ($params) {
      $url = "https://admin-" . TENANT_KEY . ".sindiapp.app.br/reset-password/{$params['token']}";
      header("Location: {$url}");
      die;
    }, false);

    // MY ACCOUNT ENDPOINTS:
    $this->addEndpoint('GET', '/v1/my-account', function () {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate()) return $this->response->withStatus(401);

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

    // USER PROFILES ENDPOINTS:
    $this->addEndpoint('GET', '/v1/profiles/?userKey?', function (Request $r) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate()) return $this->response->withStatus(401);

      // Validate user permissions:
      $this->getService('iam/permission')->validatePermissions([
        'IAM_USER' => 'R',
        'IAM_ACCESSPROFILE' => 'R',
        'IAM_ACCESSPROFILE_USER' => 'R'
      ]);

      $key = $r->getRoute()->params['userKey'];

      $result = $this->getService('iam/user')->userProfiles($key);

      return $this->response->withData($result);
    });

    $this->addEndpoint('POST', '/v1/profiles/?userKey?', function (Request $r) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate()) return $this->response->withStatus(401);

      // Validate user permissions:
      $this->getService('iam/permission')->validatePermissions([
        'IAM_USER' => 'R',
        'IAM_ACCESSPROFILE' => 'R',
        'IAM_ACCESSPROFILE_USER' => 'CRUD'
      ]);

      $key = $r->getRoute()->params['userKey'];
      $user = $this->getService('iam/user')->get(['ds_key' => $key]);
      if (empty($user)) throw new NotFound("O usuário não foi encontrado.");

      $profiles = json_decode($r->getBody('selected_profiles'), true);
      if (empty($profiles)) throw new BadRequest("Forneça os perfis de acesso do usuário.");

      $result = $this->getService('iam/user')->updUserProfiles($user->id_iam_user, $profiles);

      return $this->response->withData($result);
    });
  }
}
