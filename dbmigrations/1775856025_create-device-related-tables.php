<?php

namespace Iam\Migrations;

use SplitPHP\DbManager\Migration;
use SplitPHP\Database\DbVocab;

class CreateDeviceRelatedTables extends Migration
{
  public function apply()
  {
    // DEVICE Table
    $this->Table('IAM_DEVICE')

      // Fields
      ->id('id_iam_device')
      ->string('ds_key', 17)
      ->datetime('dt_created')->setDefaultValue(DbVocab::SQL_CURTIMESTAMP())
      ->text('tx_useragent_info')

      // Indexes
      ->Index('KEY', DbVocab::IDX_UNIQUE)->onColumn('ds_key')
    ;

    // DEVICE_SESSION Table
    $this->Table('IAM_DEVICE_SESSION')

      // Fields
      ->id('id_iam_device_session')
      ->string('ds_key', 17)
      ->datetime('dt_created')->setDefaultValue(DbVocab::SQL_CURTIMESTAMP())
      ->fk('id_iam_device')
      ->fk('id_iam_session')

      // Indexes
      ->Index('KEY', DbVocab::IDX_UNIQUE)->onColumn('ds_key')

      // Foreign Keys
      ->Foreign('id_iam_device')->references('id_iam_device')->atTable('IAM_DEVICE')->onDelete(DbVocab::FKACTION_CASCADE)->onUpdate(DbVocab::FKACTION_CASCADE)
      ->Foreign('id_iam_session')->references('id_iam_session')->atTable('IAM_SESSION')->onDelete(DbVocab::FKACTION_CASCADE)->onUpdate(DbVocab::FKACTION_CASCADE)
    ;
  }
}
