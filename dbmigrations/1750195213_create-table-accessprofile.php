<?php

namespace Iam\Migrations;

use SplitPHP\DbManager\Migration;
use SplitPHP\Database\DbVocab;

class CreateTableAccessprofile extends Migration
{
  public function apply()
  {
    $this->Table('IAM_ACCESSPROFILE', 'Access Profile')
      ->id('id_iam_accessprofile')
      ->string('ds_key', 17)
      ->datetime('dt_created')->setDefaultValue(DbVocab::SQL_CURTIMESTAMP())
      ->datetime('dt_updated')->nullable()->setDefaultValue(null)
      ->int('id_iam_user_created')->nullable()->setDefaultValue(null)
      ->int('id_iam_user_updated')->nullable()->setDefaultValue(null)
      ->string('ds_title', 60)
      ->string('ds_tag', 10)->nullable()->setDefaultValue(null)
      ->text('tx_description')->nullable()->setDefaultValue(null)
      ->Index('KEY', DbVocab::IDX_UNIQUE)->onColumn('ds_key')
      ->Foreign('id_iam_user_created')->references('id_iam_user')->atTable('IAM_USER')->onUpdate(DbVocab::FKACTION_CASCADE)->onDelete(DbVocab::FKACTION_SETNULL)
      ->Foreign('id_iam_user_updated')->references('id_iam_user')->atTable('IAM_USER')->onUpdate(DbVocab::FKACTION_CASCADE)->onDelete(DbVocab::FKACTION_SETNULL);

    $this->Table('IAM_ACCESSPROFILE_USER', "User's Access Profile")
      ->id('id_iam_accessprofile_user')
      ->int('id_iam_accessprofile')
      ->int('id_iam_user')
      ->int('id_iam_user_created')->nullable()->setDefaultValue(null)
      ->Foreign('id_iam_accessprofile')->references('id_iam_accessprofile')->atTable('IAM_ACCESSPROFILE')->onUpdate(DbVocab::FKACTION_CASCADE)->onDelete(DbVocab::FKACTION_CASCADE)
      ->Foreign('id_iam_user')->references('id_iam_user')->atTable('IAM_USER')->onUpdate(DbVocab::FKACTION_CASCADE)->onDelete(DbVocab::FKACTION_CASCADE)
      ->Foreign('id_iam_user_created')->references('id_iam_user')->atTable('IAM_USER')->onUpdate(DbVocab::FKACTION_CASCADE)->onDelete(DbVocab::FKACTION_SETNULL);
  }
}
