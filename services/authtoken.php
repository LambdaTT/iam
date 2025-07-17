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
namespace Iam\Services;

use SplitPHP\Service;
use SplitPHP\Utils;

class Authtoken extends Service
{
  private $purgePeriod;
  private $purgeObsolete;

  public function __construct()
  {
    $this->purgeObsolete = true; // If false, does not perform obsolete tokens purging.
    $this->purgePeriod = 180; // Number of days in which any obsolete (expired and consumed) tokens will be purged.

    if ($this->purgeObsolete) $this->purgeObsolete();
  }

  // Create a new authtoken with the specified expiration time:
  public function create(string $userKey, ?int $expiration = null)
  {
    $tokenObj = (object) [
      "ds_hash" => Utils::dataEncrypt($userKey, PRIVATE_KEY)
    ];

    // If no expiration time were provided, the token won't expire
    if (!is_null($expiration)) $tokenObj->dt_expires = date('Y-m-d H:i:s', ($expiration + time()));

    return $this->getDao('IAM_AUTHTOKEN')
      ->insert($tokenObj);
  }

  // Mark a token, identified by its hash as "consumed". Consumed tokens cannot be used anymore.
  public function consume($tokenHash)
  {
    return $this->getDao('IAM_AUTHTOKEN')
      ->filter('ds_hash')->equalsTo($tokenHash)
      ->update([
        "do_consumed" => 'Y',
        "dt_updated" => date('Y-m-d H:i:s')
      ]);
  }

  // Authenticate a token, identified by its hash. If the authentication succeed, returns true, else returns false.
  public function authenticate($tokenHash)
  {
    $tkn = $this->get($tokenHash);

    // If token is already consumed, authentication fails
    if (empty($tkn) || $tkn->do_consumed == 'Y') return false;

    // If tokens is expired, authentication fails
    if (!empty($tkn->dt_expires))
      if ($tkn->dt_expires < date('Y-m-d H:i:s')) return false;

    return true;
  }

  public function getTokenUser($tokenHash)
  {
    $usrKey = Utils::dataDecrypt($tokenHash, PRIVATE_KEY);

    return $this->getService('iam/user')->get(['ds_key' => $usrKey]);
  }

  // Get a single token, identified by its hash, from the database. If no token were found, returns null.
  private function get($tokenHash)
  {
    return $this->getDao('IAM_AUTHTOKEN')
      ->filter('ds_hash')->equalsTo($tokenHash)
      ->first();
  }

  private function purgeObsolete()
  {
    // Purge period in seconds:
    $period = $this->purgePeriod * 24 * 60 * 60;

    // Obsolete tokens created before this date will be purged:
    $lastDate = date('Y-m-d H:i:s', time() - $period);

    $this->getDao('IAM_AUTHTOKEN')
      ->filter('do_consumed')->equalsTo('Y')
      ->and('dt_created')->lessThan($lastDate)
      ->delete();

    $this->getDao('IAM_AUTHTOKEN')
      ->filter('dt_expires')->lessThan(date('Y-m-d H:i:s'))
      ->and('dt_created')->lessThan($lastDate)
      ->delete();
  }
}
