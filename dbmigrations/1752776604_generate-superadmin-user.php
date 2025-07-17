<?php

namespace Iam\Migrations;

use SplitPHP\DbManager\Migration;

class GenerateSuperadminUser extends Migration
{
  public function apply()
  {
    $this->Table('IAM_USER')
      ->Seed(1)
      ->onField('ds_key', true)->setByFunction(fn() => 'usr-' . uniqid())
      ->onField('ds_email')->setFixedValue('system@admin.com')
      ->onField('ds_password')->setByFunction(fn() => password_hash('admin', PASSWORD_DEFAULT))
      ->onField('ds_first_name')->setFixedValue('Super')
      ->onField('ds_last_name')->setFixedValue('Administrator')
      ->onField('do_session_expires')->setFixedValue('N')
      ->onField('do_is_superadmin')->setFixedValue('Y')
      ->onField('do_hidden')->setFixedValue('Y');
  }
}
