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
use Exception;
use SplitPHP\Database\Dbmetadata;

class User extends Service
{
  private int $pswdLvl = 0; // Password level, default is 0 (no requirements)

  public function init()
  {
    $this->setConfigs();
  }

  public function setPswdLvl(int $pswdLvl)
  {
    if ($pswdLvl < 0) $pswdLvl = 0;
    if ($pswdLvl > 3) $pswdLvl = 3; // 0 = no requirements, 1 = uppercase, 2 = numbers, 3 = special
    $this->pswdLvl = $pswdLvl;
  }

  // Get a list of registered users, based on parameters.
  public function list($params, $listAdmin = false)
  {
    if (!$listAdmin) {
      $params['do_is_superadmin'] = 'N';
    }
    return $this->getDao('IAM_USER')
      ->bindParams($params)
      ->find(
        "SELECT
          usr.ds_first_name,
          usr.ds_last_name,
          usr.ds_email,
          usr.dt_last_access,
          usr.ds_key,
          usr.do_active,
          usr.id_iam_user,
          usr.dt_created,
          CONCAT(usr.ds_first_name, ' ', usr.ds_last_name) as fullName,
          DATE_FORMAT(usr.dt_last_access, '%d/%m/%Y %T') as dtLastAccess,
          fle.ds_url as ds_avatar_img_url,
          usr.do_is_superadmin,
          usr.do_hidden
        FROM `IAM_USER` usr
        LEFT JOIN `FMN_FILE` fle ON (fle.id_fmn_file = usr.id_fmn_file_avatar)
        "
      );
  }

  // Get a single user data, based on parameters. If no user is found, returns null.
  public function get($params = [])
  {
    $user = $this->getDao('IAM_USER')
      ->bindParams($params)
      ->first(
        "SELECT 
            usr.*, 
            fle.ds_url as ds_avatar_img_url,
            CONCAT(usr.ds_first_name, ' ', usr.ds_last_name) as fullName,
            CONCAT(usrc.ds_first_name, ' ', usrc.ds_last_name) as userCreated, 
            DATE_FORMAT(usr.dt_created, '%d/%m/%Y %T') as dtCreated,  
            CONCAT(usru.ds_first_name, ' ', usru.ds_last_name) as userUpdated, 
            DATE_FORMAT(usr.dt_updated, '%d/%m/%Y %T') as dtUpdated 
            FROM `IAM_USER` usr 
            LEFT JOIN `IAM_USER` usrc ON (usrc.id_iam_user = usr.id_iam_user_created) 
            LEFT JOIN `IAM_USER` usru ON (usru.id_iam_user = usr.id_iam_user_updated)
            LEFT JOIN `FMN_FILE` fle ON (fle.id_fmn_file = usr.id_fmn_file_avatar)
            "
      );

    if (!empty($user)) {
      $userProfiles = $this->getDao('IAM_ACCESSPROFILE_USER')
        ->filter('userId')->equalsTo($user->id_iam_user)
        ->find(
          "SELECT 
              prf.ds_key 
            FROM `IAM_ACCESSPROFILE_USER` rel 
            JOIN `IAM_ACCESSPROFILE` prf ON (prf.id_iam_accessprofile = rel.id_iam_accessprofile)
            WHERE rel.id_iam_user = ?userId? "
        );
      $user->selected_profiles = [];
      foreach ($userProfiles as $prf) {
        $user->selected_profiles[] = $prf->ds_key;
      }
    }

    return $user;
  }

  // Creates a new user register.
  public function create($data)
  {
    // Validates new user email and password:
    $this->validateEmail($data);
    $this->validatePassword($data['ds_password']);

    // Removes forbidden fields from $data:
    $data = $this->getService('utils/misc')->dataBlacklist($data, [
      'id_iam_user',
      'ds_key',
      'dt_last_access',
      'id_iam_user_created',
      'id_iam_user_updated',
      'dt_created',
      'dt_updated'
    ]);

    // Treat Avatar img file upload:
    if (!empty($data['user_avatar'])) {
      $avatarFile = $this->getService('filemanager/file')
        ->create($data['user_avatar']['name'], $data['user_avatar']['path'], 'Y');
      if (!empty($avatarFile))
        $data['id_fmn_file_avatar'] = $avatarFile->id_fmn_file;
    }

    $this->filterUserData($data);

    // Set default values:
    $data['ds_key'] = 'usr-' . uniqid();
    $loggedUser = $this->getService('iam/session')->getLoggedUser();
    $data['id_iam_user_created'] = empty($loggedUser) ? null : $loggedUser->id_iam_user;
    $data['ds_password'] = password_hash($data['ds_password'], PASSWORD_DEFAULT);

    return $this->getDao('IAM_USER')->insert($data);
  }

  // Updates an user, identified by parameters, with the passed data.
  public function updUser($params, $data)
  {
    // Retrieves user's data.
    $usr = $this->get($params);

    // Validates input email:
    if (!empty($data['ds_email'])) {
      $this->validateEmail($data, $usr->id_iam_user);
    }

    // Validates input password:
    if (!empty($data['ds_password'])) {
      $this->validatePassword($data['ds_password']);
      $data['ds_password'] = hash('sha256', $data['ds_password']);
    } else unset($data['ds_password']);

    // Removes forbidden fields from $data:
    $data = $this->getService('utils/misc')->dataBlacklist($data, [
      'id_iam_user',
      'ds_key',
      'id_iam_user_created',
      'id_iam_user_updated',
      'dt_created',
      'dt_updated'
    ]);

    $data = $this->filterUserData($data);

    // Sets default values:
    $loggedUsr = $this->getService('iam/session')->getLoggedUser();
    $data['id_iam_user_updated'] = !empty($loggedUsr) ? $loggedUsr->id_iam_user : $usr->id_iam_user;
    $data['dt_updated'] = date('Y-m-d H:i:s');

    // Treats Avatar img file upload:
    if (!empty($data['erase_avatar'])) {
      if (!empty($usr->id_fmn_file_avatar)) {
        $avatarFile = $this->getService('filemanager/file')->get(['id_fmn_file' => $usr->id_fmn_file_avatar]);
        if (!empty($avatarFile)) $this->getService('filemanager/file')->remove(['id_fmn_file' => $usr->id_fmn_file_avatar]);

        $data['id_fmn_file_avatar'] = null;
      }

      unset($data['erase_avatar']);
    } elseif (!empty($_FILES['user_avatar'])) {
      if (!empty($usr->id_fmn_file_avatar)) {
        $avatarFile = $this->getService('filemanager/file')->get(['id_fmn_file' => $usr->id_fmn_file_avatar]);
        if (!empty($avatarFile)) $this->getService('filemanager/file')->remove(['id_fmn_file' => $usr->id_fmn_file_avatar]);
        unset($avatarFile);
      }

      $avatarFile = $this->getService('filemanager/file')
        ->create($_FILES['user_avatar']['name'], $_FILES['user_avatar']['tmp_name'], 'Y');
      if (!empty($avatarFile))
        $data['id_fmn_file_avatar'] = $avatarFile->id_fmn_file;
    }

    return $this->getDao('IAM_USER')
      ->filter('id_iam_user')->equalsTo($usr->id_iam_user)
      ->update($data);
  }

  // Removes an user, identified by parameters, from the database.
  public function remove($params)
  {
    // Retrieves user's data.
    $usr = $this->get($params);

    // Removes eventual user's avatar img file:
    if (!empty($usr->id_fmn_file_avatar)) {
      $avatarFile = $this->getService('filemanager/file')->get(['id_fmn_file' => $usr->id_fmn_file_avatar]);
      if (!empty($avatarFile)) $this->getService('filemanager/file')->remove(['id_fmn_file' => $usr->id_fmn_file_avatar]);
    }

    // Deletes user's data from the database.
    return $this->getDao('IAM_USER')
      ->filter('id_iam_user')->equalsTo($usr->id_iam_user)
      ->delete();
  }

  // List all profiles attached to an user identified by its unique key.
  public function userProfiles($userKey)
  {
    return $this->getDao('IAM_ACCESSPROFILE')
      ->filter('user_key')->equalsTo($userKey)
      ->find('iam/userprofiles');
  }

  // Updates user's profiles setup
  public function updUserProfiles(int $userId, array $profiles)
  {
    // Clear user profiles setup:
    $this->getDao('IAM_ACCESSPROFILE_USER')
      ->filter('id_iam_user')->equalsTo($userId)
      ->delete();

    $results = [];
    // Re-create user's profiles setup.
    foreach ($profiles as $prf) {
      $prf = (array)$prf; // Ensure $prf is an array
      $prf = $this->getService('iam/accessprofile')->get(['ds_key' => $prf['ds_key']]);

      $results[] = $this->getDao('IAM_ACCESSPROFILE_USER')->insert([
        'id_iam_user' => $userId,
        'id_iam_accessprofile' => $prf->id_iam_accessprofile
      ]);
    }

    return $results;
  }

  // Sends a "change password" e-mail to the user identified by its e-mail address.
  public function requestPasswordReset($params)
  {
    // Check for a valid e-mail address
    if (filter_var($params['ds_email'], FILTER_VALIDATE_EMAIL) === false) throw new Exception("Forneça um e-mail válido.", BAD_REQUEST);

    // Get user data by its e-mail address. If no user is found, throws exception.
    $user = $this->get(['ds_email' => $params['ds_email']]);
    if (empty($user)) throw new Exception("E-mail inexistente.", VALIDATION_FAILED);

    // Creates a new authentication token.
    $token = $this->getService('iam/authtoken')->create($user->ds_key, 60 * 60 * 24); // 24 hours

    // Generates URL which points to the route of the change password page
    $url = RESETPASS_URL . (substr(RESETPASS_URL, -1) == '/' ? '' : '/') . $token->ds_hash;

    // Sends e-mail with a link to perform password reset.
    $content = $this->renderTemplate('iam/mail/recoverpass', compact(['url', 'user']));
    $subject = "Recuperar acesso - " . APPLICATION_NAME;

    $this->getService('utils/mail')
      ->send($content, $user->ds_email, $subject);

    return $token;
  }

  /**
   * Validates the e-mail address of a user.
   * @param array $data The data containing the e-mail address to validate.
   * @param ?int $userId The ID of the user to exclude from the validation check (optional).
   * @throws Exception If the e-mail is invalid or already registered by another user.
   */
  private function validateEmail($data, $userId = null)
  {
    // Check if the e-mail contains the proper string pattern.
    if (filter_var($data['ds_email'], FILTER_VALIDATE_EMAIL) === false) throw new Exception("Forneça um e-mail válido.", VALIDATION_FAILED);

    // Check if there is another user registered with the same e-mail address.
    $sql = "SELECT id_iam_user FROM `IAM_USER` WHERE ds_email = ?ds_email? ";
    $dbData = $this->getDao('IAM_USER')
      ->filter('ds_email')->equalsTo($data['ds_email']);

    if (!empty($userId)) {
      $sql .= "AND id_iam_user != ?id_iam_user?";
      $dbData = $dbData->and('id_iam_user')->differentFrom($userId);
    }

    $dbData = $dbData->find($sql);

    if (!empty($dbData)) throw new Exception("Já existe outro usuário cadastrado com este e-mail.", CONFLICT);
  }

  /**
   * Validates the password based on the set password level.
   * Password levels:
   * 0 - No requirements (minimum length of 3 characters)
   * 1 - At least one uppercase letter
   * 2 - Level 1 + At least one number
   * 3 - Level 2 + At least one special character (!@#$_)
   * @param ?string $password The password to validate.
   * @throws Exception If the password does not meet the security requirements.
   */
  private function validatePassword(?string $password = null)
  {
    $failure = false;

    if (empty($password) || strlen($password) < 3) {
      $failure = true;
    }

    if ($this->pswdLvl > 0) {
      if (empty(preg_match('/[A-Z]/m', $password))) $failure = true;

      if (empty(preg_match('/[a-z]/m', $password))) $failure = true;
    }

    if ($this->pswdLvl > 1) {
      if (empty(preg_match('/[0-9]/m', $password))) $failure = true;
    }

    if ($this->pswdLvl > 2) {
      if (empty(preg_match('/[!@#$_]/m', $password))) $failure = true;
    }

    if ($failure) throw new Exception('A senha fornecida não atende aos requisitos de segurança.', VALIDATION_FAILED);
  }

  /**
   *  Remove fields in $data that are not user-related
   *  @param  array $data The array to be filtered
   *  @return array  An array containing only user-related fields
   */
  public function filterUserData(&$data)
  {
    require_once CORE_PATH . '/database/' . DBTYPE . '/class.dbmetadata.php';
    $tbInfo = Dbmetadata::tbInfo('IAM_USER');

    $data = $this->getService('utils/misc')->dataWhiteList($data, array_map(function ($c) {
      return $c['Field'];
    }, $tbInfo['columns']));

    return $data;
  }

  /**
   * Clean the content of $data, removing any user-related information.
   * @param   array $data The array to be cleaned
   * @return  array Returns the cleaned $data array
   */
  public function removeUserData(&$data)
  {
    require_once CORE_PATH . '/database/' . DBTYPE . '/class.dbmetadata.php';
    $tbInfo = Dbmetadata::tbInfo('IAM_USER');

    $data = $this->getService('utils/misc')->dataBlackList($data, array_map(function ($c) {
      return $c['Field'];
    }, $tbInfo['columns']));

    return $data;
  }
  /**
   * Check if there are any user-related fields in $data
   * @param   array $data The array to be searched
   * @return  bool Returns true if any user-related field is found, or false otherwise.
   */
  public function hasUserData($data)
  {
    $userFields = [
      'ds_email',
      'ds_password',
      'ds_first_name',
      'ds_last_name',
      'ds_phone1',
      'ds_phone2',
      'ds_company',
      'id_fmn_file_avatar',
      'do_hidden',
    ];
    return !empty(array_intersect_key(array_flip($userFields), $data));
  }

  public function attachAccessProfile($userParams, $profileParams)
  {
    $users = $this->list($userParams);
    $profiles = $this->getService('iam/accessprofile')->list($profileParams);
    $results = [];

    foreach ($users as $usr) {
      foreach ($profiles as $prf) {
        $results[] = $this->getDao('IAM_ACCESSPROFILE_USER')->insert([
          'id_iam_user' => $usr->id_iam_user,
          'id_iam_accessprofile' => $prf->id_iam_accessprofile
        ]);
      }
    }

    return $results;
  }

  private function setConfigs()
  {
    if (!defined('RESETPASS_URL'))
      define('RESETPASS_URL', getenv('RESETPASS_URL'));
  }
}
