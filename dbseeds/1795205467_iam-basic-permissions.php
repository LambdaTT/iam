<?php

namespace Iam\Seeds;

use SplitPHP\DbManager\Seed;

class IamBasicPermissions extends Seed
{
  public function apply()
  {
    /**
     * Here goes your seed's statements. For example, the following code
     * populates a table called 'Person', with 100 rows, passing along the desired values and patterns
     * in each field:
     */

    $this->SeedTable('IAM_PERMISSION', batchSize: 1)
      ->onField('ds_title')->setFixedValue('Ler Informações de Usuários')
      ->onField('ds_tag')->setFixedValue('iam.users.read')
    ;

    $this->SeedTable('IAM_PERMISSION', batchSize: 1)
      ->onField('ds_title')->setFixedValue('Gerenciar Informações de Usuários')
      ->onField('ds_tag')->setFixedValue('iam.users.manage')
    ;

    $this->SeedTable('IAM_PERMISSION', batchSize: 1)
      ->onField('ds_title')->setFixedValue('Ler Informações de Perfis de Acesso')
      ->onField('ds_tag')->setFixedValue('iam.accessprofiles.read')
    ;

    $this->SeedTable('IAM_PERMISSION', batchSize: 1)
      ->onField('ds_title')->setFixedValue('Gerenciar Informações de Perfis de Acesso')
      ->onField('ds_tag')->setFixedValue('iam.accessprofiles.manage')
    ;

    $this->SeedTable('IAM_PERMISSION', batchSize: 1)
      ->onField('ds_title')->setFixedValue('Ler Informações de Permissões')
      ->onField('ds_tag')->setFixedValue('iam.permissions.read')
    ;

    $this->SeedTable('IAM_PERMISSION', batchSize: 1)
      ->onField('ds_title')->setFixedValue('Gerenciar Informações de Permissões')
      ->onField('ds_tag')->setFixedValue('iam.permissions.manage')
    ;
  }
}
