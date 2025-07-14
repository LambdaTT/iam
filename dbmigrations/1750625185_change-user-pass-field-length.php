<?php

namespace Iam\Migrations;

use SplitPHP\DbManager\Migration;
use SplitPHP\Database\DbVocab;

class ChangeUserPassFieldLength extends Migration
{
  public function apply()
  {
    $this->Table('IAM_USER')
      ->string('ds_password', 60)->nullable()->setDefaultValue(null);
  }
}
