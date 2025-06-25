<?php

namespace Iam\Migrations;

use SplitPHP\DbMigrations\Migration;
use SplitPHP\Database\DbVocab;

class CreateAccessProfileAssociations extends Migration
{
  public function apply()
  {
    $this->Table('IAM_ACCESSPROFILE_MODULE', 'Module on Access Profile')
      ->id('id_iam_accessprofile_module')
      ->int('id_iam_accessprofile')
      ->int('id_mdc_module')
      ->Foreign('id_mdc_module')->references('id_mdc_module')->atTable('MDC_MODULE')->onUpdate(DbVocab::FKACTION_CASCADE)->onDelete(DbVocab::FKACTION_CASCADE)
      ->Foreign('id_iam_accessprofile')->references('id_iam_accessprofile')->atTable('IAM_ACCESSPROFILE')->onUpdate(DbVocab::FKACTION_CASCADE)->onDelete(DbVocab::FKACTION_CASCADE);

    $this->Table('IAM_ACCESSPROFILE_PERMISSION', 'Permission on Access Profile')
      ->id('id_iam_accessprofile_permission')
      ->string('ds_key', 17)
      ->int('id_iam_accessprofile_module')
      ->int('id_mdc_module_entity')
      ->string('do_read')->setDefaultValue('Y')
      ->string('do_create')->setDefaultValue('Y')
      ->string('do_update')->setDefaultValue('Y')
      ->string('do_delete')->setDefaultValue('Y')
      ->Index('KEY', DbVocab::IDX_UNIQUE)->onColumn('ds_key')
      ->Foreign('id_mdc_module_entity')->references('id_mdc_module_entity')->atTable('MDC_MODULE_ENTITY')->onUpdate(DbVocab::FKACTION_CASCADE)->onDelete(DbVocab::FKACTION_CASCADE)
      ->Foreign('id_iam_accessprofile_module')->references('id_iam_accessprofile_module')->atTable('IAM_ACCESSPROFILE_MODULE')->onUpdate(DbVocab::FKACTION_CASCADE)->onDelete(DbVocab::FKACTION_CASCADE);
  }
}
