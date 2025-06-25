<?php

namespace Iam\Commands;

use SplitPHP\Cli;
use SplitPHP\Utils;

class Help extends Cli
{
  public function init()
  {
    $this->addCommand('', function () {
      /** @var \Utils\Services\CliHelper $helper */
      $helper = $this->getService('utils/clihelper');

      // Header
      Utils::printLn($helper->ansi(
        strtoupper("Welcome to the IAM Help Center!"),
        'color: magenta; font-weight: bold'
      ));

      // Intro
      Utils::printLn("\nThis CLI is organized into two sub-modules:");

      // 1) Define each sub-module’s summary + commands + sub‐help pointer
      $modules = [
        'users' => [
          'summary' => 'Manage IAM users: list, create (incl. superadmin), remove, and adjust access profiles.',
          'helpCmd' => 'iam:users:help',
          'commands' => [
            'iam:users:list                — Page through existing users',
            'iam:users:create              — Interactively create a new user',
            'iam:users:generate:superadmin — Generate a new superadmin user',
            'iam:users:remove              — Delete a user by its ID',
            'iam:users:set:access-profiles — Add or remove access profiles for a user',
          ],
        ],
        'accessprofiles' => [
          'summary' => 'Manage IAM access profiles: list, create, and remove profiles.',
          'helpCmd' => 'iam:accessprofiles:help',
          'commands' => [
            'iam:accessprofiles:list   — Page through existing access profiles',
            'iam:accessprofiles:create — Interactively create a new access profile',
            'iam:accessprofiles:remove — Delete an access profile by its ID',
          ],
        ],
      ];

      // 2) Summary table of modules
      Utils::printLn($helper->ansi("\nAvailable sub-modules:\n", 'color: cyan; text-decoration: underline'));
      $rows = [];
      foreach ($modules as $name => $m) {
        $rows[] = [
          'module' => $name,
          'desc'   => rtrim($m['summary'], '.'),
          'help'   => $m['helpCmd'],
        ];
      }
      $helper->table($rows, [
        'module' => 'Module',
        'desc'   => 'What it does',
        'help'   => 'Help screen',
      ]);

      // 3) Per‐module simple command list + pointer to detailed help
      foreach ($modules as $name => $m) {
        Utils::printLn($helper->ansi("\n{$name}", 'color: yellow; font-weight: bold'));
        $helper->listItems($m['commands'], false, '    •');
        Utils::printLn("  For more detail, run {$m['helpCmd']}");
      }

      Utils::printLn(''); // trailing newline
    });
  }
}
