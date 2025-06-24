<?php

namespace Iam\Commands;

use SplitPHP\Cli;
use SplitPHP\Utils;

class Accessprofiles extends Cli
{
  public function init()
  {
    $this->addCommand('list', function ($args) {
      // Extract and normalize our options
      $limit   = isset($args['--limit']) ? (int)$args['--limit'] : 10;
      $sortBy  = $args['--sort-by']         ?? null;
      $sortDir = $args['--sort-direction']  ?? 'ASC';
      unset($args['--limit'], $args['--sort-by'], $args['--sort-direction']);

      $page = isset($args['--page']) ? (int)$args['--page'] : 1;
      unset($args['--page']);

      // --- <== HERE: open STDIN in BLOCKING mode (no stream_set_blocking) ===>
      $stdin = fopen('php://stdin', 'r');
      // on *nix, disable line buffering & echo
      if (DIRECTORY_SEPARATOR !== '\\') {
        system('stty -icanon -echo');
      }

      $exit = false;
      while (! $exit) {
        // Clear screen + move cursor home
        if (DIRECTORY_SEPARATOR === '\\') {
          system('cls');
        } else {
          echo "\033[2J\033[H";
        }

        // Header & hints
        Utils::printLn($this->getService('utils/clihelper')->ansi("Welcome to the IAM Access Profiles List Command!\n", 'color: cyan; font-weight: bold'));
        Utils::printLn("HINTS:");
        Utils::printLn("  • --limit={$limit}   (items/page)");
        Utils::printLn("  • --sort-by={$sortBy}   --sort-direction={$sortDir}");
        if (DIRECTORY_SEPARATOR === '\\') {
          Utils::printLn("  • Press 'n' = next page, 'p' = previous page, 'q' = quit");
        } else {
          Utils::printLn("  • ←/→ arrows to navigate pages, 'q' to quit");
        }
        Utils::printLn("  • Press 'ctrl+c' to exit at any time");
        Utils::printLn();

        // Fetch & render
        $params = array_merge($args, [
          '$limit' => $limit,
          '$limit_multiplier' => 1, // No multiplier for pagination
          '$page'  => $page,
        ]);
        if ($sortBy) {
          $params['$sort_by']        = $sortBy;
          $params['$sort_direction'] = $sortDir;
        }

        $rows = $this->getService('iam/accessprofile')->list($params);

        if (empty($rows)) {
          Utils::printLn("  >> No access profiles found on page {$page}.");
        } else {
          Utils::printLn(" Page {$page} — showing " . count($rows) . " items");
          Utils::printLn(str_repeat('─', 60));
          $this->getService('utils/clihelper')->table($rows, [
            'id_iam_accessprofile'      => 'ID',
            'ds_title'                  => 'Title',
            'tx_description'            => 'Description',
            'ds_tag'                    => 'Tag',
          ]);
        }

        // --- <== HERE: wait for exactly one keypress, blocking until you press ===>
        $c = fgetc($stdin);
        if (DIRECTORY_SEPARATOR === '\\') {
          $input = strtolower($c);
        } else {
          if ($c === "\033") {             // arrow keys start with ESC
            $input = $c . fgetc($stdin) . fgetc($stdin);
          } else {
            $input = $c;
          }
        }

        // Handle navigation
        if (DIRECTORY_SEPARATOR === '\\') {
          switch ($input) {
            case 'n':
              $page++;
              break;
            case 'p':
              $page = max(1, $page - 1);
              break;
            case 'q':
              $exit = true;
              break;
          }
        } else {
          switch ($input) {
            case "\033[C": // →
              $page++;
              break;
            case "\033[D": // ←
              $page = max(1, $page - 1);
              break;
            case 'q':
              $exit = true;
              break;
          }
        }
      }

      // Restore terminal settings on *nix
      if (DIRECTORY_SEPARATOR !== '\\') {
        system('stty sane');
      }

      // Cleanup
      fclose($stdin);
    });

    $this->addCommand('create', function () {
      Utils::printLn("Welcome to the Iam Access Profile Create Command!");
      Utils::printLn("This command will help you create a new access profile in the IAM system.");
      Utils::printLn();
      Utils::printLn(" >> Please follow the prompts to define your access profile informations.");
      Utils::printLn();
      Utils::printLn("  >> New Access Profile:");
      Utils::printLn("------------------------------------------------------");

      $profile = $this->getService('utils/clihelper')->inputForm([
        'ds_title' => [
          'label' => 'Title',
          'required' => true,
          'length' => 60,
        ],
        'tx_description' => [
          'label' => 'Description',
        ],
        'ds_tag' => [
          'label' => 'Tag',
          'required' => false,
          'length' => 10,
        ],
      ]);

      $record = $this->getService('iam/accessprofile')->create($profile);

      Utils::printLn("  >> Iam Access Profile created successfully!");
      foreach ($record as $key => $value) {
        Utils::printLn("    -> {$key}: {$value}");
      }
    });

    $this->addCommand('remove', function () {
      Utils::printLn("Welcome to the Iam Access Profile Removal Command!");
      Utils::printLn();
      $profileId = readline("  >> Please, enter the Access Profile ID you want to remove: ");

      $this->getService('iam/accessprofile')->remove([
        'id_iam_accessprofile' => $profileId,
      ]);
      Utils::printLn("  >> Access Profile with ID {$profileId} removed successfully!");
    });

    $this->addCommand('set:permissions', function(){
      
    });

    $this->addCommand('help', function () {
      /** @var \Utils\Services\CliHelper $helper */
      $helper = $this->getService('utils/clihelper');
      Utils::printLn($helper->ansi(strtoupper("Welcome to the Iam Access Profile Help Center!"), 'color: magenta; font-weight: bold'));

      // 1) Define metadata for each command
      $commands = [
        'accessprofiles:list'   => [
          'usage' => 'iam:accessprofiles:list [--limit=<n>] [--sort-by=<field>] [--sort-direction=<dir>] [--page=<n>]',
          'desc'  => 'Page through existing access profiles.',
          'flags' => [
            '--limit=<n>'          => 'Items per page (default 10)',
            '--sort-by=<field>'    => 'Field to sort by',
            '--sort-direction=<d>' => 'ASC or DESC (default ASC)',
            '--page=<n>'           => 'Page number (default 1)',
          ],
        ],
        'accessprofiles:create' => [
          'usage' => 'iam:accessprofiles:create',
          'desc'  => 'Interactively create a new access profile.',
        ],
        'accessprofiles:remove' => [
          'usage' => 'iam:accessprofiles:remove',
          'desc'  => 'Delete an access profile by its ID.',
        ],
        'accessprofiles:help'             => [
          'usage' => 'iam:accessprofiles:help',
          'desc'  => 'Show this help screen.',
        ],
      ];

      // 2) Summary table
      Utils::printLn($helper->ansi("\nAvailable commands:\n", 'color: cyan; text-decoration: underline'));

      $rows = [
        [
          'cmd'  => 'iam:accessprofiles:list',
          'desc' => 'Page through existing access profiles',
          'opts' => '--limit, --sort-by, --sort-direction, --page',
        ],
        [
          'cmd'  => 'iam:accessprofiles:create',
          'desc' => 'Interactively create a new access profile',
          'opts' => '(no flags)',
        ],
        [
          'cmd'  => 'iam:accessprofiles:remove',
          'desc' => 'Delete an access profile by ID',
          'opts' => '(no flags)',
        ],
      ];

      $helper->table($rows, [
        'cmd'  => 'Command',
        'desc' => 'Description',
        'opts' => 'Options',
      ]);

      // 3) Detailed usage lists
      foreach ($commands as $cmd => $meta) {
        Utils::printLn($helper->ansi("\n{$cmd}", 'color: yellow; font-weight: bold'));
        Utils::printLn("  Usage:   {$meta['usage']}");
        Utils::printLn("  Purpose: {$meta['desc']}");

        if (!empty($meta['flags'])) {
          Utils::printLn("  Options:");
          $flagLines = [];
          foreach ($meta['flags'] as $flag => $explain) {
            $flagLines[] = "{$flag}  — {$explain}";
          }
          $helper->listItems($flagLines, false, '    •');
        }
      }

      Utils::printLn(''); // trailing newline
    });
  }
}
// EOF