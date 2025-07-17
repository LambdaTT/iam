<?php

namespace Iam\Routes;

use SplitPHP\WebService;
use SplitPHP\Request;

class Permissions extends WebService
{
  public function init(): void
  {
    $this->addEndpoint('GET', '/v1/user-permissions', function () {
      // auth user login:
      if (!$this->getService('iam/session')->authenticate()) return $this->response->withStatus(401);

      $user = $this->getService('iam/session')->getLoggedUser();

      $data = [
        'isSuperAdmin' => $user->do_is_superadmin,
        'regularPermissions' => $this->getDao('IAM_ACCESSPROFILE_PERMISSION')
          ->filter('id_iam_user')->equalsTo($user->id_iam_user)
          ->find("iam/permissionsofuser"),
        'customPermissions' => $this->getDao('IAM_ACCESSPROFILE_PERMISSION')
          ->filter('id_iam_user')->equalsTo($user->id_iam_user)
          ->find("iam/custompermissionsofuser"),
      ];

      return $this->response
        ->withStatus(200)
        ->withData($data);
    });

    $this->addEndpoint('POST', '/v1/permission', function (Request $r) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate()) return $this->response->withStatus(401);

      // Validate user permissions:
      $this->getService('iam/permission')->validatePermissions([
        'IAM_CUSTOM_PERMISSION' => 'C'
      ]);

      $data = $r->getBody();

      $result = $this->getService('iam/permission')->createExecPermission($data);

      return $this->response
        ->withStatus(201)
        ->withData($result);
    });
  }
}
