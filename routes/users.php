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

class Users extends WebService
{
  public function init()
  {
    // USER PROFILES ENDPOINTS:
    $this->addEndpoint('GET', '/v1/profiles/?userKey?', function ($params) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate()) return $this->response->withStatus(401);

      // Validate user permissions:
      $this->getService('iam/permission')->validatePermissions([
        'IAM_USER' => 'R',
        'IAM_ACCESSPROFILE' => 'R',
        'IAM_ACCESSPROFILE_USER' => 'R'
      ]);

      $result = $this->getService('iam/user')->userProfiles($params['userKey']);

      return $this->response->withData($result);
    });

    // USER ENDPOINTS:
    $this->addEndpoint('GET', '/v1/user/?userKey?', function ($params) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate()) return $this->response->withStatus(401);

      // Validate user permissions:
      $this->getService('iam/permission')->validatePermissions([
        'IAM_USER' => 'R'
      ]);

      $data = $this->getService('iam/user')->get(['ds_key' => $params['userKey'], 'do_hidden' => 'N']);
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

      if (!empty($profiles))
        $newUser->profiles = $this->getService('iam/user')->updUserProfiles($newUser->id_iam_user, $profiles);
      else throw new Exception("Adicione ao menos um Perfil ao usuário.", BAD_REQUEST);

      return $this->response
        ->withStatus(201)
        ->withData($newUser);
    });

    $this->addEndpoint('POST', '/v2/user', function ($data) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate()) return $this->response->withStatus(401);

      // Validate user permissions:
      $this->getService('iam/permission')->validatePermissions([
        'IAM_USER' => 'C',
        'IAM_ACCESSPROFILE' => 'R',
        'IAM_ACCESSPROFILE_USER' => 'CD'
      ]);

      $profiles = $data['selected_profiles'];
      unset($data['selected_profiles']);

      $newUser = $this->getService('iam/user')->create($data);

      if (!empty($profiles))
        $newUser->profiles = $this->getService('iam/user')->updUserProfiles($newUser->id_iam_user, $profiles);
      else throw new Exception("Adicione ao menos um Perfil ao usuário.", BAD_REQUEST);

      return $this->response
        ->withStatus(201)
        ->withData($newUser);
    });

    $this->addEndpoint('PUT', '/v1/user/?userKey?', function ($params) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate())
        return $this->response->withStatus(401);

      // Validate user permissions:
      $this->getService('iam/permission')->validatePermissions([
        'IAM_USER' => 'U',
        'IAM_ACCESSPROFILE' => 'R',
        'IAM_ACCESSPROFILE_USER' => 'CD'
      ]);

      $userKey = $params['userKey'];
      unset($params['userKey']);

      $user = $this->getService('iam/user')->get(['ds_key' => $userKey]);
      if (empty($user)) throw new Exception("Usuário inválido", BAD_REQUEST);

      $profiles = json_decode($params['selected_profiles'], true);
      unset($params['selected_profiles']);

      $params['do_hidden'] = 'N';
      $rows = $this->getService('iam/user')->updUser(['ds_key' => $userKey], $params);
      if ($rows < 1) return $this->response->withStatus(404);

      if (!empty($profiles)) $this->getService('iam/user')->updUserProfiles($user->id_iam_user, $profiles);
      else throw new Exception("Adicione ao menos um Perfil ao usuário.", BAD_REQUEST);

      return $this->response->withStatus(204);
    });

    $this->addEndpoint('PUT', '/v2/user/?userKey?', function ($params) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate())
        return $this->response->withStatus(401);

      // Validate user permissions:
      $this->getService('iam/permission')->validatePermissions([
        'IAM_USER' => 'U',
        'IAM_ACCESSPROFILE' => 'R',
        'IAM_ACCESSPROFILE_USER' => 'CD'
      ]);

      $userKey = $params['userKey'];
      unset($params['userKey']);

      $user = $this->getService('iam/user')->get(['ds_key' => $userKey]);
      if (empty($user)) throw new Exception("Usuário inválido", BAD_REQUEST);

      $profiles = $params['selected_profiles'];
      unset($params['selected_profiles']);

      $params['do_hidden'] = 'N';
      $rows = $this->getService('iam/user')->updUser(['ds_key' => $userKey], $params);
      if ($rows < 1) return $this->response->withStatus(404);

      if (!empty($profiles)) $this->getService('iam/user')->updUserProfiles($user->id_iam_user, $profiles);
      else throw new Exception("Adicione ao menos um Perfil ao usuário.", BAD_REQUEST);

      return $this->response->withStatus(204);
    });

    $this->addEndpoint('PUT', '/v1/change-pass/?authtoken?', function ($data) {
      // Auth user login:
      if (!$this->getService('iam/authtoken')->authenticate($data['authtoken']))
        return $this->response->withStatus(401);

      $user = $this->getService('iam/authtoken')->getTokenUser($data['authtoken']);
      if (empty($user)) throw new Exception("Usuário inválido", BAD_REQUEST);

      $this->getService('iam/authtoken')->consume($data['authtoken']);
      $data = [
        'ds_password' => $data['ds_password']
      ];

      $rows = $this->getService('iam/user')->updUser(['ds_key' => $user->ds_key], $data);
      if ($rows < 1) return $this->response->withStatus(404);

      return $this->response->withStatus(204);
    }, false);

    $this->addEndpoint('DELETE', '/v1/user/?userKey?', function ($params) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate()) return $this->response->withStatus(401);

      // Validate user permissions:
      $this->getService('iam/permission')->validatePermissions([
        'IAM_USER' => 'D'
      ]);

      $result = $this->getService('iam/user')->remove(['ds_key' => $params['userKey'], 'do_hidden' => 'N']);
      if ($result < 1) return $this->response->withStatus(404);

      return $this->response->withStatus(204);
    });

    $this->addEndpoint('POST', '/v1/request-password-reset', function ($params) {
      return $this->response
        ->withStatus(200)
        ->withData($this->getService('iam/user')->requestPasswordReset($params));
    }, false);

    $this->addEndpoint('GET', '/redirect/reset-password/?token?', function ($params) {
      $url = "https://admin-" . TENANT_KEY . ".sindiapp.app.br/reset-password/{$params['token']}";
      header("Location: {$url}");
      die;
    }, false);

    // MY ACCOUNT ENDPOINTS:
    $this->addEndpoint('GET', '/v1/my-account', function ($data) {
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
  }
}
