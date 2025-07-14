<?php

namespace Iam\Migrations;

use SplitPHP\DbManager\Migration;
use SplitPHP\Database\DbVocab;

class CreateTableAuthtoken extends Migration
{
  public function apply()
  {
    $this->Table('IAM_AUTHTOKEN', 'Auth Token')
      ->id('id_iam_authtoken')
      ->datetime('dt_created')->setDefaultValue(DbVocab::SQL_CURTIMESTAMP())
      ->datetime('dt_updated')->nullable()->setDefaultValue(null)
      ->int('id_iam_user_created')->nullable()->setDefaultValue(null)
      ->int('id_iam_user_updated')->nullable()->setDefaultValue(null)
      ->string('ds_hash', 120)
      ->string('do_consumed', 1)->setDefaultValue('N')
      ->datetime('dt_expires')->nullable()->setDefaultValue(null)
      ->Index('KEY', DbVocab::IDX_UNIQUE)->onColumn('ds_hash')
      ->Foreign('id_iam_user_created')->references('id_iam_user')->atTable('IAM_USER')->onUpdate(DbVocab::FKACTION_CASCADE)->onDelete(DbVocab::FKACTION_SETNULL)
      ->Foreign('id_iam_user_updated')->references('id_iam_user')->atTable('IAM_USER')->onUpdate(DbVocab::FKACTION_CASCADE)->onDelete(DbVocab::FKACTION_SETNULL);
  }
}
