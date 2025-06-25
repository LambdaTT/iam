<?php

namespace Iam\Migrations;

use SplitPHP\DbMigrations\Migration;
use SplitPHP\Database\DbVocab;

class CreateTableSession extends Migration
{
  public function apply()
  {
    $this->Table('IAM_SESSION', 'IAM Session')
      ->id('id_iam_session')
      ->string('ds_key', 17)
      ->datetime('dt_created')->setDefaultValue(DbVocab::SQL_CURTIMESTAMP())
      ->datetime('dt_updated')->nullable()->setDefaultValue(null)
      ->string('ds_sso_session_key')->nullable()->setDefaultValue(null)
      ->int('id_iam_user')
      ->string('ds_ip')
      ->Index('KEY', DbVocab::IDX_UNIQUE)->onColumn('ds_key')
      ->Foreign('id_iam_user')->references('id_iam_user')->atTable('IAM_USER')->onUpdate(DbVocab::FKACTION_CASCADE)->onDelete(DbVocab::FKACTION_CASCADE);
  }
}
