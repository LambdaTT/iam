<?php

namespace Iam\Migrations;

use SplitPHP\DbManager\Migration;
use SplitPHP\Database\DbVocab;

class AddMultiSessionOptionToUser extends Migration
{
  public function apply()
  {
    $this->Table('IAM_USER')->string('do_multi_session', 1)->setDefaultValue('N');
  }
}
