<?php

namespace Iam\Migrations;

use SplitPHP\DbManager\Migration;
use SplitPHP\Database\DbVocab;

class CreateTableUser extends Migration
{
  public function apply()
  {
    $this->Table('IAM_USER', 'User')
      ->id('id_iam_user')
      ->int('id_sso_userid')->nullable()->setDefaultValue(null)
      ->string('ds_key', 17)
      ->datetime('dt_created')->setDefaultValue(DbVocab::SQL_CURTIMESTAMP())
      ->datetime('dt_updated')->nullable()->setDefaultValue(null)
      ->int('id_iam_user_created')->nullable()->setDefaultValue(null)
      ->int('id_iam_user_updated')->nullable()->setDefaultValue(null)
      ->string('ds_email')
      ->string('ds_password')->nullable()->setDefaultValue(null)
      ->string('ds_first_name', 100)
      ->string('ds_last_name', 100)
      ->string('do_active', 1)->setDefaultValue('Y')
      ->int('id_fmn_file_avatar')->nullable()->setDefaultValue(null)
      ->string('ds_phone1', 20)->nullable()->setDefaultValue(null)
      ->string('ds_phone2', 20)->nullable()->setDefaultValue(null)
      ->string('ds_company', 100)->nullable()->setDefaultValue(null)
      ->datetime('dt_last_access')->nullable()->setDefaultValue(null)
      ->string('do_session_expires', 1)->setDefaultValue('Y')
      ->string('do_is_superadmin', 1)->setDefaultValue('N')
      ->string('do_hidden', 1)->setDefaultValue('N')
      ->Index('KEY', DbVocab::IDX_UNIQUE)->onColumn('ds_key')
      ->Foreign('id_iam_user_created')->references('id_iam_user')->atTable('IAM_USER')->onUpdate(DbVocab::FKACTION_CASCADE)->onDelete(DbVocab::FKACTION_SETNULL)
      ->Foreign('id_iam_user_updated')->references('id_iam_user')->atTable('IAM_USER')->onUpdate(DbVocab::FKACTION_CASCADE)->onDelete(DbVocab::FKACTION_SETNULL);
  }
}
