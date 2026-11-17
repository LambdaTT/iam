<?php

namespace Iam\Routes;

use SplitPHP\WebService;
use SplitPHP\Request;

class Permissions extends WebService
{
  public function init(): void
  {
    $this->addEndpoint('POST', '/v1/permission/?title?/?tag?', function ($title, $tag) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate()) 
        return $this->response->withStatus(401);

      // Validate user permissions:

      $permission = $this->getService('iam/permission')->create($title, $tag);

      return $this->response
        ->withStatus(201)
        ->withData($permission);
    });
  }
}
