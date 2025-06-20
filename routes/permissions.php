<?php
namespace Iam\Routes;
use SplitPHP\WebService;
class Permissions extends WebService
{
  public function init()
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
  }
}