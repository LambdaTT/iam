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
use SplitPHP\EventDispatcher;
use Exception;
use SplitPHP\Database\Database;
use SplitPHP\Database\Dbmetadata;
use SplitPHP\Exceptions\BadRequest;
use SplitPHP\Exceptions\Conflict;
use SplitPHP\Exceptions\FailedValidation;
use SplitPHP\Exceptions\NotFound;

class User extends Service
{
  private const USER_TABLE = 'IAM_USER';
  private const PRFBRIDGE_TABLE = 'IAM_ACCESSPROFILE_USER';
  private const PROFILE_TABLE = 'IAM_ACCESSPROFILE';
  private const PERMISSION_TABLE = 'IAM_PERMISSION';
  private const PRMBRIDGE_TABLE = 'IAM_ACCESSPROFILE_PERMISSION';

  private int $pswdLvl = 0; // Password level, default is 0 (no requirements)

  public function __construct()
  {
    if (!defined('RESETPASS_URL'))
      define('RESETPASS_URL', getenv('RESETPASS_URL'));

    $this->pswdLvl = getenv('IAM_PASSWORD_LVL') ?? 0;
  }

  public function setPswdLvl(int $pswdLvl): void
  {
    if ($pswdLvl < 0) $pswdLvl = 0;
    if ($pswdLvl > 3) $pswdLvl = 3; // 0 = no requirements, 1 = uppercase, 2 = numbers, 3 = special
    $this->pswdLvl = $pswdLvl;
  }

  // Get a list of registered users, based on parameters.
  public function list($params = [], $listAdmin = false, $listHidden = false): array
  {
    if (!$listAdmin) $params['do_is_superadmin'] = 'N';
    if (!$listHidden) $params['do_hidden'] = 'N';

    return $this->getDao(self::USER_TABLE)
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
  public function get($params = []): ?object
  {
    return $this->getDao(self::USER_TABLE)
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
  }

  public function requireId(array $usrFilters = []): int
  {
    if (empty($usrId = $this->get($usrFilters)?->id_iam_user))
      throw new NotFound("O usuário solicitado não foi encontrado.");

    return $usrId;
  }

  // Creates a new user register.
  public function create(array|object &$data): object
  {
    // Validates new user email and password:
    $this->validateEmail($data);
    $this->validatePassword($data['ds_password']);

    // Removes forbidden fields from $data:
    $usrdata = $this->getService('utils/misc')->dataBlacklist($data, [
      'id_iam_user',
      'ds_key',
      'dt_last_access',
      'id_iam_user_created',
      'id_iam_user_updated',
      'dt_created',
      'dt_updated'
    ]);

    // Treat Avatar img file upload:
    if (!empty($_FILES['avatar'])) {
      $avatarFile = $this->getService('filemanager/file')
        ->add($_FILES['avatar']['name'], $_FILES['avatar']['tmp_name'], 'Y');
      if (!empty($avatarFile))
        $usrdata['id_fmn_file_avatar'] = $avatarFile->id_fmn_file;
    }

    $this->filterUserData($usrdata);

    // Set default values:
    $usrdata['ds_key'] = 'usr-' . uniqid();
    $loggedUser = $this->getService('iam/session')->getLoggedUser();
    $usrdata['id_iam_user_created'] = empty($loggedUser) ? null : $loggedUser->id_iam_user;
    $usrdata['ds_password'] = password_hash($usrdata['ds_password'], PASSWORD_DEFAULT);

    $this->removeUserData($data);
    $newUser = $this->getDao(self::USER_TABLE)->insert($usrdata);

    // Notify the rest of the application that a user was created (e.g. to enroll managers into
    // the notification team). Carries the intended profile tags — see above.
    EventDispatcher::dispatch(
      fn() => true,
      'iam.user.created',
      [$newUser]
    );

    return $newUser;
  }

  // Updates an user, identified by parameters, with the passed data.
  public function updUser(array $params, array|object &$data): int
  {
    // Removes forbidden fields from $data:
    $usrdata = $this->getService('utils/misc')->dataBlacklist($data, [
      'id_iam_user',
      'ds_key',
      'id_iam_user_created',
      'id_iam_user_updated',
      'dt_created',
      'dt_updated'
    ]);

    // Retrieves user's data.
    $usr = $this->get($params);

    // Validates input email:
    if (!empty($usrdata['ds_email']))
      $this->validateEmail($usrdata, $usr->id_iam_user);

    // Validates input password:
    if (!empty($usrdata['ds_password'])) {
      $this->validatePassword($usrdata['ds_password']);
      $usrdata['ds_password'] = password_hash($usrdata['ds_password'], PASSWORD_DEFAULT);
    } else unset($usrdata['ds_password']);

    $this->filterUserData($usrdata);

    // Sets default values:
    $loggedUsr = $this->getService('iam/session')->getLoggedUser();
    $usrdata['id_iam_user_updated'] = !empty($loggedUsr) ? $loggedUsr->id_iam_user : $usr->id_iam_user;
    $usrdata['dt_updated'] = date('Y-m-d H:i:s');

    // Treats Avatar img file upload:
    if (!empty($usrdata['erase_avatar'])) {
      if (!empty($usr->id_fmn_file_avatar)) {
        $avatarFile = $this->getService('filemanager/file')->get(['id_fmn_file' => $usr->id_fmn_file_avatar]);
        if (!empty($avatarFile)) $this->getService('filemanager/file')->remove(['id_fmn_file' => $usr->id_fmn_file_avatar]);

        $usrdata['id_fmn_file_avatar'] = null;
      }

      unset($usrdata['erase_avatar']);
    } elseif (!empty($_FILES['avatar'])) {
      if (!empty($usr->id_fmn_file_avatar)) {
        $avatarFile = $this->getService('filemanager/file')->get(['id_fmn_file' => $usr->id_fmn_file_avatar]);
        if (!empty($avatarFile)) $this->getService('filemanager/file')->remove(['id_fmn_file' => $usr->id_fmn_file_avatar]);
        unset($avatarFile);
      }

      $avatarFile = $this->getService('filemanager/file')
        ->add($_FILES['avatar']['name'], $_FILES['avatar']['tmp_name'], 'Y');
      if (!empty($avatarFile))
        $usrdata['id_fmn_file_avatar'] = $avatarFile->id_fmn_file;
    }

    $this->removeUserData($data);

    return $this->getDao(self::USER_TABLE)
      ->filter('id_iam_user')->equalsTo($usr->id_iam_user)
      ->update($usrdata);
  }

  // Removes an user, identified by parameters, from the database.
  public function remove(array $params): int
  {
    // Retrieves user's data.
    $usr = $this->get($params);

    // Removes eventual user's avatar img file:
    if (!empty($usr->id_fmn_file_avatar)) {
      $avatarFile = $this->getService('filemanager/file')->get(['id_fmn_file' => $usr->id_fmn_file_avatar]);
      if (!empty($avatarFile)) $this->getService('filemanager/file')->remove(['id_fmn_file' => $usr->id_fmn_file_avatar]);
    }

    // Deletes user's data from the database.
    return $this->getDao(self::USER_TABLE)
      ->filter('id_iam_user')->equalsTo($usr->id_iam_user)
      ->delete();
  }

  public function listProfiles(array $usrFilters = []): array
  {
    $usrId = $this->requireId($usrFilters);

    $prfBridgeTbName = self::PRFBRIDGE_TABLE;
    $profileTbName = self::PROFILE_TABLE;
    return $this->getDao($prfBridgeTbName)
      ->filter('usrId')->equalsTo($usrId)
      ->find(
        "SELECT 
          prf.*,
          (CASE
            WHEN assoc.id_iam_accessprofile_user IS NOT NULL THEN 'Y'
            ELSE 'N'
           END) AS attached
        FROM `$prfBridgeTbName` assoc
        JOIN `$profileTbName` prf
        ON prf.id_iam_accessprofile = assoc.id_iam_accessprofile
        WHERE assoc.id_iam_user = ?usrId?"
      );
  }

  public function clearUserProfiles(array $usrFilters = []): bool
  {
    $usrId = $this->requireId($usrFilters);

    return $this->getDao(self::PRFBRIDGE_TABLE)
      ->filter('id_iam_user')->equalsTo($usrId)
      ->delete() > 0;
  }

  public function attachProfiles(array $usrFilters = [], array|string $prfTags = []): int
  {
    $prfTags = array_filter(
      $this->getService('iam/accessprofile')->normalizeTagList($prfTags),
      fn($t) => !in_array($t, array_map(
        fn($p) => $p->ds_tag,
        $this->listProfiles($usrFilters)
      ))
    );
    $usrIds = array_map(
      fn($usr) => $usr->id_iam_user,
      $this->list($usrFilters)
    );
    if (empty($prfTags) || empty($usrIds)) return 0;

    $prfIds = array_map(
      fn($prf) => $prf->id_iam_accessprofile,
      $this->getService('iam/accessprofile')->list(['ds_tag' => '$in|' . implode('|', $prfTags)])
    );
    if (empty($prfIds)) throw new NotFound("Os perfis de acesso solicitados não puderam ser encontrados.");

    $counter = 0;
    foreach ($usrIds as $uId) {
      $this->getDao(self::PRFBRIDGE_TABLE)
        ->insert(array_map(fn($pId) => [
          'id_iam_user' => $uId,
          'id_iam_accessprofile' => $pId
        ], $prfIds));
    }

    return $counter;
  }

  public function detachProfiles(array $usrFilters = [], array|string $prfTags = []): int
  {
    $prfTags = $this->getService('iam/accessprofile')->normalizeTagList($prfTags);
    $usrIds = array_map(
      fn($usr) => $usr->id_iam_user,
      $this->list($usrFilters)
    );
    if (empty($prfTags) || empty($usrIds)) return 0;

    $prfIds = array_map(
      fn($prf) => $prf->id_iam_accessprofile,
      $this->getService('iam/accessprofile')->list(['ds_tag' => '$in|' . implode('|', $prfTags)])
    );

    if (empty($prfIds)) throw new NotFound("Os perfis de acesso solicitados não puderam ser encontrados.");

    return $this->getDao(self::PRFBRIDGE_TABLE)
      ->filter('id_iam_user')->in($usrIds)
      ->and('id_iam_accessprofile')->in($prfIds)
      ->delete();
  }

  public function listPermissions(array $usrFilters = []): array
  {
    $usrId = $this->requireId($usrFilters);

    $prfBridgeTbName = self::PRFBRIDGE_TABLE;
    $prmBridgeTbName = self::PRMBRIDGE_TABLE;
    $prmTbName = self::PERMISSION_TABLE;
    return $this->getDao($prfBridgeTbName)
      ->filter('userId')->equalsTo($usrId)
      ->find(
        "SELECT DISTINCT 
            prm.*
          FROM `$prfBridgeTbName` prf
          JOIN `$prmBridgeTbName` assoc 
          ON prf.id_iam_accessprofile = assoc.id_iam_accessprofile
          JOIN `$prmTbName` prm
          ON assoc.id_iam_permission = prm.id_iam_permission
          WHERE prf.id_iam_user = ?userId?"
      );
  }

  // Sends a "change password" e-mail to the user identified by its e-mail address.
  public function requestPasswordReset(string $email): object
  {
    // Check for a valid e-mail address
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false)
      throw new BadRequest("Forneça um e-mail válido.");

    // Get user data by its e-mail address. If no user is found, throws exception.
    $user = $this->get(['ds_email' => $email]);
    if (empty($user)) throw new FailedValidation("E-mail inexistente.");

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
  private function validateEmail(array|object $data, ?int $userId = null): void
  {
    if (empty($data['ds_email'])) return;

    // Check if the e-mail contains the proper string pattern.
    if (filter_var($data['ds_email'], FILTER_VALIDATE_EMAIL) === false)
      throw new BadRequest("Forneça um e-mail válido.");

    // Check if there is another user registered with the same e-mail address.
    $sql = "SELECT id_iam_user FROM `IAM_USER` WHERE ds_email = ?ds_email? ";
    $dbData = $this->getDao(self::USER_TABLE)
      ->filter('ds_email')->equalsTo($data['ds_email']);

    if (!empty($userId)) {
      $sql .= "AND id_iam_user != ?id_iam_user?";
      $dbData = $dbData->and('id_iam_user')->differentFrom($userId);
    }

    $dbData = $dbData->find($sql);

    if (!empty($dbData)) throw new Conflict("Já existe outro usuário cadastrado com este e-mail.");
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
  private function validatePassword(?string $password = null): void
  {
    if (empty($password)) return;

    $failure = false;

    if (strlen($password) < 3) {
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

    if ($failure) throw new FailedValidation('A senha fornecida não atende aos requisitos de segurança.');
  }

  /**
   *  Remove fields in $data that are not user-related
   *  @param  array $data The array to be filtered
   *  @return array  An array containing only user-related fields
   */
  public function filterUserData(array|object &$data): array|object
  {
    require_once CORE_PATH . '/database/' . Database::getRdbmsName() . '/class.dbmetadata.php';
    $tbInfo = Dbmetadata::tbInfo(self::USER_TABLE);

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
  public function removeUserData(array|object &$data): array|object
  {
    require_once CORE_PATH . '/database/' . Database::getRdbmsName() . '/class.dbmetadata.php';
    $tbInfo = Dbmetadata::tbInfo(self::USER_TABLE);

    $filter = [
      ...array_map(function ($c) {
        return $c['Field'];
      }, $tbInfo['columns']),
      'avatar'
    ];

    $data = $this->getService('utils/misc')->dataBlackList($data, $filter);

    return $data;
  }
  /**
   * Check if there are any user-related fields in $data
   * @param   array $data The array to be searched
   * @return  bool Returns true if any user-related field is found, or false otherwise.
   */
  public function hasUserData(array|object $data): bool
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
}
