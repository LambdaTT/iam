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
use SplitPHP\Request;
use Exception;

class Session extends Service
{
  private const SESSION_TIMEOUT = 1800; // 30 minutes

  // Performs the user authentication:
  public function authenticate($update = true)
  {
    // Retrieves session data. If no session is found, the authentication fails.
    $session = $this->get();
    if (empty($session)) return false;

    // Get logged user data. If user is invalid or inactive, shut down session
    $user = $this->getService('iam/user')->get(['id_iam_user' => $session->id_iam_user]);
    if (empty($user) || $user->do_active != 'Y') {
      $this->logout();
      return false;
    }

    // If an expirable session is idle for half an hour or more, shut down session.
    if ($this->isSessionExpired()) {
      $this->logout();
      return false;
    }

    if ($update) $this->updSession();

    return true;
  }

  // Performs login for an user identified by credentials.
  public function login($params)
  {
    // Checks credentials sent by the client.
    if (filter_var($params['ds_email'], FILTER_VALIDATE_EMAIL) === false || empty($params['ds_password'])) throw new Exception("Forneça as credenciais corretamente.", BAD_REQUEST);

    // Get user information based on sent credentials.
    $credentials = [
      "ds_email" => $params['ds_email'],
      "ds_password" => hash('sha256', $params['ds_password']),
      "do_active" => 'Y'
    ];

    $user = $this->getService('iam/user')->get($credentials);

    // Check if the credentials refers to a valid user
    if (empty($user)) throw new Exception("Não foi possível fazer login com as credenciais fornecidas.", VALIDATION_FAILED);

    // Performs login, creating a session for the user identified.
    return $this->create($user);
  }

  // Performs login for an user identified by credentials sent by an SSO
  public function loginSSO($credentials)
  {
    // Retrieves credentials from token sent by SSO
    $credentials = unserialize(Utils::dataDecrypt($credentials, PUBLIC_KEY));

    // Checks if the application's secret key, only known by the SSO and the application itself is correct. 
    if (empty($credentials) || $credentials['applicationSecret'] != PRIVATE_KEY) throw new Exception("Invalid credentials", NOT_AUTHORIZED);

    // Get user data. If no user were found, throws exception.
    $usr = $this->getService('iam/user')->get(['id_sso_userid' => $credentials->userID]);
    if (empty($usr)) throw new Exception("Invalid user", NOT_AUTHORIZED);

    // Performs login, creating a session for the user identified.
    return $this->create($usr, $credentials['ssoSessionKey']);
  }

  public function loginByAuthToken($token)
  {
    $user = $this->getService('iam/user')->get(['ds_key' => Utils::dataDecrypt($token, PRIVATE_KEY)]);
    if (empty($user)) throw new Exception("Não foi possível efetuar o login.", VALIDATION_FAILED);

    $session = $this->create($user);

    if (!empty($session)) $this->getService('iam/authtoken')->consume($token);

    return $session;
  }

  // Shut down an user session
  public function logout($params = [])
  {
    // Consumes autologin token:
    if (!empty($params) && !empty($params['token'])) $this->getService('iam/authtoken')->consume($params['token']);

    // Identify session key sent by the client.
    $sessionKey = $this->getKey();
    if (empty($sessionKey)) return false;

    // Deletes user session from the database.
    $this->remove(["ds_key" => $sessionKey]);
    return true;
  }

  // Get data from the current logged user.
  public function getLoggedUser()
  {
    // Get data from the current session
    $session = $this->get();
    if (empty($session)) return null;

    // Get user based on session's data.
    $user = $this->getService('iam/user')->get(['id_iam_user' => $session->id_iam_user]);
    if (empty($user) || $user->do_active != 'Y') {
      $this->logout();
      return null;
    }

    // Check id session expired:
    if ($this->isSessionExpired()) {
      $this->logout();
      return null;
    }

    return $user;
  }

  // Get data from the current session. If no session were found, returns null.
  public function get()
  {
    // Identify session key sent by the client.
    $sessionKey = $this->getKey();

    if (empty($sessionKey)) return null;

    // Check if the session credentials are valid.
    $this->validateCredentials();

    // Retrieves session data from the database. If no session were found, returns null.
    return $this->getDao('IAM_SESSION')
      ->filter('ds_key')->equalsTo($sessionKey)
      ->first();
  }

  private function create($user, $ssoSessionKey = null)
  {
    // Remove all previous user sessions:
    $this->remove([
      "id_iam_user" => $user->id_iam_user
    ]);

    // Create new session:
    $session = $this->getDao('IAM_SESSION')
      ->insert([
        "ds_key" => "ses-" . uniqid(),
        "ds_sso_session_key" => $ssoSessionKey,
        "id_iam_user" => $user->id_iam_user,
        "ds_ip" => Request::getUserIP()
      ]);

    // Register login: 
    $this->getService('iam/user')->updUser(["id_iam_user" => $user->id_iam_user], ['dt_last_access' => date('Y-m-d H:i:s')]);
    return $session;
  }

  // Updates the current session.
  private function updSession()
  {
    // Identify session key sent by the client.
    $sessionKey = $this->getKey();

    if (empty($sessionKey)) return false;

    $data = [
      'dt_updated' => date('Y-m-d H:i:s')
    ];

    $this->getDao('IAM_SESSION')
      ->filter('ds_key')->equalsTo($sessionKey)
      ->update($data);
  }

  // Check if the session credentials are valid.
  private function validateCredentials()
  {
    // Defines the rules for valid session's key and XSRF token.
    $validRules = [
      "ds_key" => (object) [
        "dataType" => 'string',
        "pattern" => '/ses\-([a-z]|[0-9]){13}/m',
        "length" => 17
      ]
    ];

    // Identify session key sent by the client.
    $sessionKey = $this->getKey();

    // Check if the session's key and/or XSRF token sent by the client match the defined rules.
    $params = [];
    if (!empty($sessionKey)) $params['ds_key'] = $sessionKey;

    if (Utils::validateData($validRules, $params) == false) throw new Exception("Invalid input.", 400);
  }

  // Deletes user sessions from the database, based on parameters.
  private function remove($params)
  {
    // If no parameter is passed, throws exception.
    if (empty($params)) throw new Exception("Removal params required.");

    return $this->getDao('IAM_SESSION')
      ->bindParams($params)
      ->delete();
  }

  // Identify session key sent by the client. If it's not on the cookies or on the headers, returns null.
  private function getKey()
  {
    if (!empty($_SERVER['HTTP_IAM_SESSION_KEY'])) {
      return $_SERVER['HTTP_IAM_SESSION_KEY'];
    } else if (!empty($_COOKIE['iam_session_key'])) {
      return $_COOKIE['iam_session_key'];
    } else {
      return null;
    }
  }

  private function isSessionExpired()
  {
    // Get Session Data:
    $session = $this->get();

    // Get logged user data:
    $user = $this->getService('iam/user')->get(['id_iam_user' => $session->id_iam_user]);
    if ($user->do_session_expires != 'Y') return false;

    // Check if session is expired:
    return (!empty($session->dt_updated) && (time() - strtotime($session->dt_updated) > self::SESSION_TIMEOUT)) || (empty($session->dt_updated) && (time() - strtotime($session->dt_created) > self::SESSION_TIMEOUT));
  }
}
