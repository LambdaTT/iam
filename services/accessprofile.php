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
use SplitPHP\Exceptions\NotFound;

class Accessprofile extends Service
{

  private const PROFILE_TABLE = 'IAM_ACCESSPROFILE';
  private const PRMBRIDGE_TABLE = 'IAM_ACCESSPROFILE_PERMISSION';
  private const PERMISSION_TABLE = 'IAM_PERMISSION';

  // List all profiles, based on parameters.
  public function list(array $params = []): array
  {
    $prfTbName = self::PROFILE_TABLE;

    return $this->getDao($prfTbName)
      ->bindParams($params)
      ->find(
        "SELECT 
          id_iam_accessprofile, 
          ds_title, 
          ds_tag, 
          dt_created, 
          ds_key 
        FROM `$prfTbName` "
      );
  }

  // Get, based on parameters, a single access profile on the database. If no profile were found, return null. 
  public function get(array $params = []): ?object
  {
    $prfTbName = self::PROFILE_TABLE;

    return $this->getDao($prfTbName)
      ->bindParams($params)
      ->first(
        "SELECT 
            prf.*, 
            CONCAT(usrc.ds_first_name, ' ', usrc.ds_last_name) as userCreated, 
            DATE_FORMAT(prf.dt_created, '%d/%m/%Y %T') as dtCreated,  
            CONCAT(usru.ds_first_name, ' ', usru.ds_last_name) as userUpdated, 
            DATE_FORMAT(prf.dt_updated, '%d/%m/%Y %T') as dtUpdated 
            FROM `$prfTbName` prf 
            LEFT JOIN `IAM_USER` usrc ON (usrc.id_iam_user = prf.id_iam_user_created) 
            LEFT JOIN `IAM_USER` usru ON (usru.id_iam_user = prf.id_iam_user_updated)"
      );
  }

  public function requireId(array $prfFilters = []): int
  {
    if (empty($prfId = $this->get($prfFilters)?->id_iam_accessprofile))
      throw new NotFound("O perfil de acesso solicitado não foi encontrado.");

    return $prfId;
  }

  // Create a new access profile in the database, then returns its new register.
  public function create(array|object $data): object
  {
    // Removes forbidden fields from $data:
    $data = $this->getService('utils/misc')->dataBlacklist($data, [
      'id_iam_accessprofile',
      'ds_key',
      'do_active',
      'id_iam_user_created',
      'id_iam_user_updated',
      'dt_created',
      'dt_updated'
    ]);

    // Set default values:
    $data['ds_key'] = uniqid();
    $data['id_iam_user_created'] = $this->getService('iam/session')->getLoggedUser()?->id_iam_user;

    return $this->getDao(self::PROFILE_TABLE)->insert($data);
  }

  // Update access profiles in the database, based on parameters.
  public function upd(array $params, array|object $data): int
  {
    // Removes forbidden fields from $data:
    $data = $this->getService('utils/misc')->dataBlacklist($data, [
      'id_iam_accessprofile',
      'ds_key',
      'id_iam_user_created',
      'id_iam_user_updated',
      'dt_created',
      'do_active',
      'dt_updated'
    ]);

    // Set default values:
    $data['id_iam_user_updated'] = $this->getService('iam/session')->getLoggedUser()->id_iam_user;
    $data['dt_updated'] = date('Y-m-d H:i:s');

    // Perform Access Profile update:
    return $this->getDao(self::PROFILE_TABLE)
      ->bindParams($params)
      ->update($data);
  }

  // Delete access profiles in the database, based on parameters.
  public function remove(array $params = []): int
  {
    return $this->getDao(self::PROFILE_TABLE)
      ->bindParams($params)
      ->delete();
  }

  public function listPermissions(array $prfFilters = []): array
  {
    $prfId = $this->requireId($prfFilters);

    $prmBridgeTbName = self::PRMBRIDGE_TABLE;
    $permissionTbName = self::PERMISSION_TABLE;
    return $this->getDao($prmBridgeTbName)
      ->filter('prfId')->equalsTo($prfId)
      ->find(
        "SELECT 
          prm.*,
          (CASE
            WHEN assoc.id_iam_accessprofile_permission IS NOT NULL THEN 'Y'
            ELSE 'N'
           END) AS attached
        FROM `$prmBridgeTbName` assoc
        RIGHT JOIN `$permissionTbName` prm
        ON prm.id_iam_permission = assoc.id_iam_permission
        AND assoc.id_iam_accessprofile = ?prfId?"
      );
  }

  public function clearPermissions(array $prfFilters = []): bool
  {
    $prfId = $this->requireId($prfFilters);

    return $this->getDao(self::PRMBRIDGE_TABLE)
      ->filter('id_iam_accessprofile')->equalsTo($prfId)
      ->delete() > 0;
  }

  public function attachPermissions(array $prfFilters = [], array|string $prmTags = []): int
  {
    $prmTags = array_filter(
      $this->getService('iam/permission')->normalizeTagList($prmTags),
      fn($t) => !in_array($t, array_map(
        fn($p) => $p->ds_tag,
        $this->listPermissions($prfFilters)
      ))
    );
    $prfIds = array_map(
      fn($prf) => $prf->id_iam_accessprofile,
      $this->list($prfFilters)
    );
    if (empty($prmTags) || empty($prfIds)) return 0;

    $prmIds = array_map(
      fn($prm) => $prm->id_iam_permission,
      $this->getService('iam/permission')->list(['ds_tag' => '$in|' . implode('|', $prmTags)])
    );
    if (empty($prmIds)) throw new NotFound("As permissões solicitadas não foram encontradas.");

    $counter = 0;
    foreach ($prfIds as $prfId) {
      $this->getDao(self::PRMBRIDGE_TABLE)
        ->insert(array_map(fn($prmId) => [
          'id_iam_accessprofile' => $prfId,
          'id_iam_permission' => $prmId
        ], $prmIds));
    }

    return $counter;
  }

  public function detachPermissions(array $prfFilters = [], array|string $prmTags = []): int
  {
    $prmTags = $this->getService('iam/permission')->normalizeTagList($prmTags);
    $prfIds = array_map(
      fn($prf) => $prf->id_iam_accessprofile,
      $this->list($prfFilters)
    );
    if (empty($prmTags) || empty($prfIds)) return 0;

    $prmIds = array_map(
      fn($prm) => $prm->id_iam_permission,
      $this->getService('iam/permission')->list(['ds_tag' => '$in|' . implode('|', $prmTags)])
    );

    if (empty($prmIds)) throw new NotFound("As permissões solicitadas não foram encontradas.");

    return $this->getDao(self::PRMBRIDGE_TABLE)
      ->filter('id_iam_accessprofile')->in($prfIds)
      ->and('id_iam_permission')->in($prmIds)
      ->delete();
  }

  public function normalizeTagList(array|string $tags): array
  {
    return array_filter(
      is_string($tags) ?
        [$this->normalizeSingleTag($tags)] :
        array_map(fn($t) => $this->normalizeSingleTag($t), $tags)
    );
  }

  public function normalizeSingleTag(string $tag): ?string
  {
    $normalized = strtolower(trim($tag));

    return $normalized === '0' || !empty($normalized) ? $normalized : null;
  }
}
