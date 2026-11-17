<?php

namespace Iam\Migrations;

use SplitPHP\DbManager\Migration;
use SplitPHP\Database\DbVocab;

class NewPermissionStandardsTable extends Migration{
  public function apply(){
    $this->Table('IAM_ACCESSPROFILE_PERMISSION')->drop();
    
    $this->Table('IAM_PERMISSION')
      ->id('id_iam_permission')
      ->datetime('dt_created')->setDefaultValue(DbVocab::SQL_CURTIMESTAMP())
      ->fk('id_iam_user_created')->nullable()->setDefaultValue(null)
      ->string('ds_title', 100)
      ->string('ds_tag', 100)
      ->Index('KEY', DbVocab::IDX_UNIQUE)->onColumn('ds_tag')
      ->Foreign('id_iam_user_created')->references('id_iam_user')->atTable('IAM_USER')->onUpdate(DbVocab::FKACTION_CASCADE)->onDelete(DbVocab::FKACTION_SETNULL)
    ;
  }
}