<?php

namespace Iam\Routes;

use SplitPHP\WebService;
use SplitPHP\Request;

class Permissions extends WebService
{
  private const PERMISSION_PERMISSIONS = [
    'read' => 'iam.permissions.read',
    'manage' => 'iam.permissions.manage'
  ];

  public function init(): void
  {
    $this->addEndpoint('GET', '/v1/permission', function ($params) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate())
        return $this->response->withStatus(401);

      // Validate user permissions:
      $this->getService('iam/permission')->requireAny(self::PERMISSION_PERMISSIONS);

      $permissions = $this->getService('iam/permission')->list($params);

      return $this->response
        ->withStatus(200)
        ->withData($permissions);
    });

    $this->addEndpoint('POST', '/v1/permission/?title?/?tag?', function ($title, $tag) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate())
        return $this->response->withStatus(401);

      // Validate user permissions:
      $this->getService('iam/permission')->requireAll(self::PERMISSION_PERMISSIONS['manage']);

      $permission = $this->getService('iam/permission')->create($title, $tag);

      return $this->response
        ->withStatus(201)
        ->withData($permission);
    });

    $this->addEndpoint('DELETE', '/v1/permission/?key?', function ($key) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate())
        return $this->response->withStatus(401);

      // Validate user permissions:
      $this->getService('iam/permission')->requireAll(self::PERMISSION_PERMISSIONS['manage']);

      $code = match ($this->getService('iam/permission')->remove(['ds_key' => $key]) > 0) {
        true => 204,
        false => 404
      };

      return $this->response->withStatus($code);
    });
  }
}
