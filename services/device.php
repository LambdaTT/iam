<?php

namespace Iam\Services;

use SplitPHP\Service;
use SplitPHP\Utils;

class Device extends Service
{
  const TABLE_DEVICE = "IAM_DEVICE";

  public function list($params = [])
  {
    return $this->getDao(self::TABLE_DEVICE)
      ->bindParams($params)
      ->find();
  }

  public function get($filters)
  {
    return $this->getDao(self::TABLE_DEVICE)
      ->bindParams($filters)
      ->first();
  }

  public function create($key = null)
  {
    // Set default values
    $data['ds_key'] = $key ?? 'dvc-' . uniqid();
    $data['tx_useragent_info'] = $_SERVER['HTTP_USER_AGENT'] ?? null;
    return $this->getDao(self::TABLE_DEVICE)->insert($data);
  }

  public function remove($params)
  {
    return $this->getDao(self::TABLE_DEVICE)
      ->bindParams($params)
      ->delete();
  }

  public function getDeviceKey()
  {
    $key = null;
    if (!empty($_SERVER['HTTP_IAM_DEVICE_KEY'])) {
      $key = $_SERVER['HTTP_IAM_DEVICE_KEY'];
    } else if (!empty($_COOKIE['iam_device_key'])) {
      $key = $_COOKIE['iam_device_key'];
    }

    $key = $key ? Utils::dataDecrypt($key, PRIVATE_KEY) : null;

    return $key;
  }

  public function getDeviceOfUser($filters)
  {
    return $this->getDao(self::TABLE_DEVICE)
      ->bindParams($filters)
      ->first('iam/devices/ofusers');
  }

  public function getDevicesOfUser($filters)
  {
    return $this->getDao(self::TABLE_DEVICE)
      ->bindParams($filters)
      ->find('iam/devices/ofusers');
  }
}
