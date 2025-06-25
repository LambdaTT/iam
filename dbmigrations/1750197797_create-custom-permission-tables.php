<?php

namespace Iam\Migrations;

use SplitPHP\DbMigrations\Migration;
use SplitPHP\Database\DbVocab;

class CreateCustomPermissionTables extends Migration
{
  public function apply()
  {
    $this->Table('IAM_CUSTOM_PERMISSION', 'Custom Permission')
      ->id('id_iam_custom_permission')
      ->string('ds_key', 17)
      ->datetime('dt_created')->setDefaultValue(DbVocab::SQL_CURTIMESTAMP())
      ->int('id_iam_user_created')->nullable()->setDefaultValue(null)
      ->string('ds_title', 100)
      ->int('id_mdc_module')
      ->Index('KEY', DbVocab::IDX_UNIQUE)->onColumn('ds_key')
      ->Foreign('id_mdc_module')->references('id_mdc_module')->atTable('MDC_MODULE')->onUpdate(DbVocab::FKACTION_CASCADE)->onDelete(DbVocab::FKACTION_CASCADE)
      ->Foreign('id_iam_user_created')->references('id_iam_user')->atTable('IAM_USER')->onUpdate(DbVocab::FKACTION_CASCADE)->onDelete(DbVocab::FKACTION_SETNULL);

    $this->Table('IAM_ACCESSPROFILE_CUSTOM_PERMISSION', 'Custom Permission on Access Profile')
      ->id('id_iam_accessprofile_custom_permission')
      ->datetime('dt_created')->setDefaultValue(DbVocab::SQL_CURTIMESTAMP())
      ->int('id_iam_user_created')->nullable()->setDefaultValue(null)
      ->int('id_iam_custom_permission')
      ->int('id_iam_accessprofile')
      ->Foreign('id_iam_custom_permission')->references('id_iam_custom_permission')->atTable('IAM_CUSTOM_PERMISSION')->onUpdate(DbVocab::FKACTION_CASCADE)->onDelete(DbVocab::FKACTION_CASCADE)
      ->Foreign('id_iam_accessprofile')->references('id_iam_accessprofile')->atTable('IAM_ACCESSPROFILE')->onUpdate(DbVocab::FKACTION_CASCADE)->onDelete(DbVocab::FKACTION_CASCADE)
      ->Foreign('id_iam_user_created')->references('id_iam_user')->atTable('IAM_USER')->onUpdate(DbVocab::FKACTION_CASCADE)->onDelete(DbVocab::FKACTION_SETNULL);
  }
}
