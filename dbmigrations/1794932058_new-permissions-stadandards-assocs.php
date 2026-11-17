<?php

namespace Iam\Migrations;

use SplitPHP\DbManager\Migration;
use SplitPHP\Database\DbVocab;

class NewPermissionsStadandardsAssocs extends Migration{
  public function apply(){
    $this->Table('IAM_ACCESSPROFILE_PERMISSION')
      ->id('id_iam_accessprofile_permission')
      ->datetime('dt_created')->setDefaultValue(DbVocab::SQL_CURTIMESTAMP())
      ->fk('id_iam_user_created')->nullable()->setDefaultValue(null)
      ->fk('id_iam_permission')
      ->fk('id_iam_accessprofile')
      ->Foreign('id_iam_permission')->references('id_iam_permission')->atTable('IAM_PERMISSION')->onUpdate(DbVocab::FKACTION_CASCADE)->onDelete(DbVocab::FKACTION_CASCADE)
      ->Foreign('id_iam_accessprofile')->references('id_iam_accessprofile')->atTable('IAM_ACCESSPROFILE')->onUpdate(DbVocab::FKACTION_CASCADE)->onDelete(DbVocab::FKACTION_CASCADE)
      ->Foreign('id_iam_user_created')->references('id_iam_user')->atTable('IAM_USER')->onUpdate(DbVocab::FKACTION_CASCADE)->onDelete(DbVocab::FKACTION_SETNULL);
    ;

    $this->Table('IAM_ACCESSPROFILE_CUSTOM_PERMISSION')->drop();
    $this->Table('IAM_ACCESSPROFILE_MODULE')->drop();
    $this->Table('IAM_CUSTOM_PERMISSION')->drop();
  }
}