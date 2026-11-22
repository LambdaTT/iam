<?php

namespace Iam\Migrations;

use SplitPHP\DbManager\Migration;
use SplitPHP\Database\DbVocab;

class CreateAccessProfileAssociations extends Migration
{
  public function apply()
  {
    // NOTA: as colunas id_mdc_module / id_mdc_module_entity e suas FKs para MDC_MODULE /
    // MDC_MODULE_ENTITY foram removidas (modelo CRUD legado). O módulo modcontrol do framework foi
    // descontinuado no overhaul de permissões do IAM, então essas tabelas não existem mais — mantê-las
    // quebrava o warm-up em banco novo. Estas tabelas são descartadas logo depois pelas migrations
    // 1794930739 / 1794932058 (novo modelo flat de permissões).
    $this->Table('IAM_ACCESSPROFILE_MODULE', 'Module on Access Profile')
      ->id('id_iam_accessprofile_module')
      ->fk('id_iam_accessprofile')
      ->Foreign('id_iam_accessprofile')->references('id_iam_accessprofile')->atTable('IAM_ACCESSPROFILE')->onUpdate(DbVocab::FKACTION_CASCADE)->onDelete(DbVocab::FKACTION_CASCADE);

    $this->Table('IAM_ACCESSPROFILE_PERMISSION', 'Permission on Access Profile')
      ->id('id_iam_accessprofile_permission')
      ->string('ds_key', 17)
      ->fk('id_iam_accessprofile_module')
      ->string('do_read')->setDefaultValue('Y')
      ->string('do_create')->setDefaultValue('Y')
      ->string('do_update')->setDefaultValue('Y')
      ->string('do_delete')->setDefaultValue('Y')
      ->Index('KEY', DbVocab::IDX_UNIQUE)->onColumn('ds_key')
      ->Foreign('id_iam_accessprofile_module')->references('id_iam_accessprofile_module')->atTable('IAM_ACCESSPROFILE_MODULE')->onUpdate(DbVocab::FKACTION_CASCADE)->onDelete(DbVocab::FKACTION_CASCADE);
  }
}
