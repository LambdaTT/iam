<?php

namespace Iam\Migrations;

use SplitPHP\DbManager\Migration;
use SplitPHP\Database\DbVocab;

class AddFieldUsernameOnTableUser extends Migration
{
  public function apply()
  {
    $this->Table('IAM_USER')
      ->string('ds_email', 255)->nullable()->setDefaultValue(null)
      ->string('ds_username', 255)->nullable()->setDefaultValue(null)
    ;
  }
}
