<?php

namespace Iam\Routes;

use SplitPHP\Request;
use SplitPHP\WebService;

class Applicationmodules extends WebService
{
  public function init(): void
  {
    // MODULE ENDPOINTS:
    $this->addEndpoint('GET', '/v1/module/?moduleId?', function (Request $r) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate()) return $this->response->withStatus(401);

      $params = [
        'id_mdc_module' => $r->getRoute()->params['moduleId']
      ];

      $data = $this->getService('modcontrol/control')->get($params);
      if (empty($data)) return $this->response->withStatus(404);

      return $this->response
        ->withStatus(200)
        ->withData($data);
    });

    $this->addEndpoint('GET', '/v1/module', function (Request $r) {
      // Auth user login:
      if (!$this->getService('iam/session')->authenticate()) return $this->response->withStatus(401);

      $params = $r->getBody();

      return $this->response
        ->withStatus(200)
        ->withData($this->getService('modcontrol/control')->list($params));
    });
  }
}
