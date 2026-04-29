<?php

namespace Iam\Routes;

use SplitPHP\WebService;
use SplitPHP\Utils;

class Devices extends WebService
{
  const SERVICE = 'iam/device';

  public function init()
  {
    $this->setAntiXsrfValidation(false);

    $this->addEndpoint('POST', '/v1/device', function () {
      $device = $this->getService(self::SERVICE)->create();
      $device->ds_key = Utils::dataEncrypt($device->ds_key, PRIVATE_KEY);

      return $this->response
        ->withStatus(201)
        ->withData($device);
    });
  }
}
