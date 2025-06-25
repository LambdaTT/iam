<?php

namespace Iam\Migrations;

use SplitPHP\DbMigrations\Migration;
use SplitPHP\Database\DbVocab;

class CreateTableUserTeam extends Migration
{
  public function apply()
  {
    $this->Table('IAM_USERTEAM', 'User Team')
      ->id('id_iam_userteam')
      ->string('ds_tag', 20)
      ->datetime('dt_created')->setDefaultValue(DbVocab::SQL_CURTIMESTAMP())
      ->datetime('dt_updated')->nullable()->setDefaultValue(null)
      ->int('id_iam_user_created')->nullable()->setDefaultValue(null)
      ->int('id_iam_user_updated')->nullable()->setDefaultValue(null)
      ->string('ds_name', 60)
      ->text('tx_description')->nullable()->setDefaultValue(null)
      ->Index('TAG', DbVocab::IDX_UNIQUE)->onColumn('ds_tag')
      ->Foreign('id_iam_user_created')->references('id_iam_user')->atTable('IAM_USER')->onUpdate(DbVocab::FKACTION_CASCADE)->onDelete(DbVocab::FKACTION_SETNULL)
      ->Foreign('id_iam_user_updated')->references('id_iam_user')->atTable('IAM_USER')->onUpdate(DbVocab::FKACTION_CASCADE)->onDelete(DbVocab::FKACTION_SETNULL);

    $this->Table('IAM_USERTEAM_USER', 'User on Team')
      ->id('id_iam_userteam_user')
      ->string('ds_key', 17)
      ->datetime('dt_created')->setDefaultValue(DbVocab::SQL_CURTIMESTAMP())
      ->datetime('dt_updated')->nullable()->setDefaultValue(null)
      ->int('id_iam_user_created')->nullable()->setDefaultValue(null)
      ->int('id_iam_user_updated')->nullable()->setDefaultValue(null)
      ->int('id_iam_userteam')
      ->int('id_iam_user')
      ->Index('KEY', DbVocab::IDX_UNIQUE)->onColumn('ds_key')
      ->Foreign('id_iam_userteam')->references('id_iam_userteam')->atTable('IAM_USERTEAM')->onUpdate(DbVocab::FKACTION_CASCADE)->onDelete(DbVocab::FKACTION_CASCADE)
      ->Foreign('id_iam_user')->references('id_iam_user')->atTable('IAM_USER')->onUpdate(DbVocab::FKACTION_CASCADE)->onDelete(DbVocab::FKACTION_CASCADE)
      ->Foreign('id_iam_user_created')->references('id_iam_user')->atTable('IAM_USER')->onUpdate(DbVocab::FKACTION_CASCADE)->onDelete(DbVocab::FKACTION_SETNULL)
      ->Foreign('id_iam_user_updated')->references('id_iam_user')->atTable('IAM_USER')->onUpdate(DbVocab::FKACTION_CASCADE)->onDelete(DbVocab::FKACTION_SETNULL);
  }
}
