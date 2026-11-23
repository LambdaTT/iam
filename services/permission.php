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
use SplitPHP\Exceptions\BadRequest;
use SplitPHP\Exceptions\Conflict;
use SplitPHP\Exceptions\Forbidden;

class Permission extends Service
{
  private const PERMISSION_TABLE = 'IAM_PERMISSION';
  private const PRFBRIDGE_TABLE = 'IAM_ACCESSPROFILE_PERMISSION';
  private const USRBRIDGE_TABLE = 'IAM_ACCESSPROFILE_USER';

  public function list(array $filters = []): array
  {
    return $this->getDao(self::PERMISSION_TABLE)
      ->bindParams($filters)
      ->find();
  }
  public function get(array $filters = []): ?object
  {
    return $this->getDao(self::PERMISSION_TABLE)
      ->bindParams($filters)
      ->first();
  }

  // Create a new custom(execution) permission.
  public function create(string $title, ?string $tag = null): object
  {
    $title = trim($title);
    if ($title == '') throw new BadRequest('Não é possível criar permissões sem título.');

    $tag = $tag && (trim($tag) != '') ?
      str_replace(' ', '.', trim(strtolower($tag))) :
      $this->genTagFromTitle($title);

    $this->checkForTagConflict($tag);

    return $this->getDao(self::PERMISSION_TABLE)
      ->insert([
        'id_iam_user_created' => $this->getService('iam/session')->getLoggedUser()?->id_iam_user,
        'ds_title' => $title,
        'ds_tag' => $tag,
      ]);
  }

  public function remove(array $filters): int
  {
    return $this->getDao(self::PERMISSION_TABLE)
      ->bindParams($filters)
      ->delete();
  }

  // Validate if the logged user has ay of the specified permission:
  public function requireAny(string|array $prmTags, bool $throwException = true): bool
  {
    // If there's no logged user, validation fails:
    $user = $this->getService('iam/session')->getLoggedUser();
    if (empty($user)) {
      if ($throwException) throw new Forbidden("Você não possui as permissões necessárias para executar esta ação");
      else return false;
    }

    // If logged user is super admin, validation succeeds:
    if ($user->do_is_superadmin == 'Y') return true;

    $prmTags = array_filter(is_string($prmTags) ? [$prmTags] : array_values($prmTags));
    if (empty($prmTags)) return true;

    $hasPermission = !empty($this->findUserPermissionTags($prmTags, $user->id_iam_user));

    if (!$hasPermission) {
      if ($throwException) throw new Forbidden("Você não possui as permissões necessárias para executar esta ação");
      else return false;
    }

    return true;
  }

  public function requireAll(string|array $prmTags, bool $throwException = true): bool
  {
    // If there's no logged user, validation fails:
    $user = $this->getService('iam/session')->getLoggedUser();
    if (empty($user)) {
      if ($throwException) throw new Forbidden("Você não possui as permissões necessárias para executar esta ação");
      else return false;
    }

    // If logged user is super admin, validation succeeds:
    if ($user->do_is_superadmin == 'Y') return true;

    $prmTags = array_filter(is_string($prmTags) ? [$prmTags] : array_values($prmTags));
    if (empty($prmTags)) return true;

    $hasPermission = empty(array_diff($prmTags, $this->findUserPermissionTags($prmTags, $user->id_iam_user)));

    if (!$hasPermission) {
      if ($throwException) throw new Forbidden("Você não possui as permissões necessárias para executar esta ação");
      else return false;
    }

    return true;
  }

  private function findUserPermissionTags(array $prmTags, int $userId): array
  {
    $prmTbName = self::PERMISSION_TABLE;
    $bridgeTbName = self::PRFBRIDGE_TABLE;
    $usrBridgeTbName = self::USRBRIDGE_TABLE;

    $prmTags = array_map(fn($t) => strtolower($t), $prmTags);

    return array_map(
      fn($row) => $row->ds_tag,
      $this->getDao($prmTbName)
        ->filter('prmTags')->in($prmTags)
        ->and('userId')->equalsTo($userId)
        ->find(
          "SELECT DISTINCT 
            prm.ds_tag
          FROM `$prmTbName` prm 
          JOIN `$bridgeTbName` assoc
          ON assoc.id_iam_permission = prm.id_iam_permission
          JOIN `$usrBridgeTbName` prf 
          ON prf.id_iam_accessprofile = assoc.id_iam_accessprofile
          AND prf.id_iam_user = ?userId?
          WHERE prm.ds_tag IN ?prmTags?"
        )
    );
  }

  private function checkForTagConflict(string $tag): void
  {
    $conflicting = !empty($this->getDao(self::PERMISSION_TABLE)
      ->filter('ds_tag')->equalsTo($tag)
      ->first());

    if ($conflicting) throw new Conflict("Já existe outra permissão com esta mesma tag.");
  }

  private function genTagFromTitle(string $title): string
  {
    return str_replace(' ', '.', strtolower($title));
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
