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
use SplitPHP\Exceptions\NotFound;

class Permission extends Service
{
  private const PERMISSION_TABLE = 'IAM_PERMISSION';
  private const PROFILE_TABLE = 'IAM_ACCESSPROFILE';
  private const PRFBRIDGE_TABLE = 'IAM_ACCESSPROFILE_PERMISSION';
  private const USRBRIDGE_TABLE = 'IAM_ACCESSPROFILE_USER';

  public function get($filters = []): ?object
  {
    return $this->getDao(self::PERMISSION_TABLE)
      ->bindParams($filters)
      ->first();
  }

  // Create a new custom(execution) permission.
  public function create(string $title, ?string $tag = null): object
  {
    $title = trim($title);
    if($title == '') throw new BadRequest('Não é possível criar permissões sem título.');

    $tag = $tag && (trim($tag) != '') ? 
      str_replace(' ', '.', trim($tag)) : 
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

  // Attach a custom(execution) permission to an access profile, both identified by their unique keys.
  public function associate(string $prfNeedle, string $prmTag): object
  {
    if (empty($prfId = $this->getDao(self::PROFILE_TABLE)
      ->filter('ds_key')->equalsTo($prfNeedle)
      ->or('ds_tag')->equalsTo($prfNeedle)
      ->first()?->id_iam_accessprofile)) throw new NotFound("O perfil de acesso solicitado não foi encontrado.");
    
    if (empty($prmId = $this->getDao(self::PERMISSION_TABLE)
      ->filter('ds_tag')->equalsTo($prmTag)
      ->first()?->id_iam_permission)) throw new NotFound("A permissão solicitada não foi encontrada.");

    return $this->getAssociation($prfId, $prmId) ?? $this->createAssociation($prfId, $prmId);
  }

  public function disassociate(string $prfNeedle, string $prmTag): int
  {
    if (empty($prf = $this->getDao(self::PROFILE_TABLE)
      ->filter('ds_key')->equalsTo($prfNeedle)
      ->or('ds_tag')->equalsTo($prfNeedle)
      ->first())) throw new NotFound("O perfil de acesso solicitado não foi encontrado.");

    if (empty($prm = $this->getDao(self::PERMISSION_TABLE)
      ->filter('ds_tag')->equalsTo($prmTag)
      ->first())) throw new NotFound("A permissão solicitada não foi encontrada.");


    return $this->getDao(self::PRFBRIDGE_TABLE)
      ->filter('id_iam_permission')->equalsTo($prm->id_iam_permission)
      ->and('id_iam_accessprofile')->equalsTo($prf->id_iam_accessprofile)
      ->delete();
  }

  // Validate if the logged user has the specified permission:
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
    
    $prmTags = is_string($prmTags) ? [$prmTags] : $prmTags;
    if (empty($prmTags)) {
      if ($throwException) throw new Forbidden("Você não possui as permissões necessárias para executar esta ação");
      else return false;
    }

    $prmTbName = self::PERMISSION_TABLE;
    $bridgeTbName = self::PRFBRIDGE_TABLE;
    $prfTbName = self::PROFILE_TABLE;
    $usrBridgeTbName = self::USRBRIDGE_TABLE;
    $hasPermission = !empty($this->getDao($prmTbName)
      ->filter('prmTags')->in($prmTags)
      ->and('userId')->equalsTo($user->id_iam_user)
      ->first(
        "SELECT DISTINCT 
            prm.id_iam_permission
          FROM `$prmTbName` prm 
          JOIN `$bridgeTbName` assoc
          ON assoc.id_iam_permission = prm.id_iam_permission
          JOIN `$usrBridgeTbName` prf 
          ON prf.id_iam_accessprofile = assoc.id_iam_accessprofile
          AND prf.id_iam_user = ?userId?
          WHERE prm.ds_tag IN ?prmTags?"
      )
    );

    if(!$hasPermission){
      if($throwException) throw new Forbidden("Você não possui as permissões necessárias para executar esta ação");
      else return false;
    }

    return true;
  }

  private function checkForTagConflict(string $tag): void
  {
    $conflicting = !empty($this->getDao(self::PERMISSION_TABLE)
      ->filter('ds_tag')->equalsTo($tag)
      ->first());

    if($conflicting) throw new Conflict("Já existe outra permissão com esta mesma tag.");
  }

  private function genTagFromTitle(string $title): string
  {
    return str_replace(' ', '.', strtolower($title));
  }

  private function getAssociation(int $prfId, int $prmId): ?object
  {
    return $this->getDao(self::PRFBRIDGE_TABLE)
      ->filter('id_iam_accessprofile')->equalsTo($prfId)
      ->and('id_iam_permission')->equalsTo($prmId)
      ->first();
  }

  private function createAssociation(int $prfId, int $prmId): ?object
  {
    return $this->getDao(self::PRFBRIDGE_TABLE)
      ->insert([
        'id_iam_accessprofile' => $prfId,
        'id_iam_permission' => $prmId,
        'id_iam_user_created' => $this->getService('iam/session')->getLoggedUser()?->id_iam_user,
      ]);
  }
}
