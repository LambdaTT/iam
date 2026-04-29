<?php

namespace Iam\Services;

use SplitPHP\Service;

class Devicesession extends Service
{
  const TABLE = "IAM_DEVICE_SESSION";

  public function list($params = [])
  {
    return $this->getDao(self::TABLE)
      ->bindParams($params)
      ->find();
  }

  public function get($params = [])
  {
    return $this->getDao(self::TABLE)
      ->bindParams($params)
      ->first();
  }

  public function create($sessionId, $deviceId)
  {
    $data = [];
    // Set default values
    $data['ds_key'] = 'dvs-' . uniqid();
    $data['id_iam_session'] = $sessionId;
    $data['id_iam_device'] = $deviceId;
    return $this->getDao(self::TABLE)->insert($data);
  }

  public function remove($params)
  {
    return $this->getDao(self::TABLE)
      ->bindParams($params)
      ->delete();
  }
}
