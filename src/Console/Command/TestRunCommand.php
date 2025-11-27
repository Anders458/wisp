<?php

namespace Wisp\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand (
   name: 'test:run',
   description: 'Run Pest tests with optional route filtering'
)]
class TestRunCommand extends Command
{
   private string $root;

   public function __construct (string $root)
   {
      parent::__construct ();
      $this->root = $root;
   }

   protected function configure (): void
   {
      $this
         ->addArgument ('method', InputArgument::OPTIONAL, 'HTTP method (GET, POST, etc.) or full route in quotes')
         ->addArgument ('path', InputArgument::OPTIONAL, 'Route path (if method provided separately)')
         ->addOption ('filter', 'f', InputOption::VALUE_REQUIRED, 'Custom Pest filter pattern')
         ->setHelp (
            <<<'HELP'
            Run Pest tests with optional filtering.

            Examples:
              <info>composer console test:run</info>                           Run all tests
              <info>composer console test:run "GET /v1/health-check"</info>   Test specific route (quoted)
              <info>composer test:route GET /v1/health-check</info>           Test specific route (unquoted)
              <info>composer console test:run --filter=validation</info>      Custom filter

            When providing a route, it will be converted to a Pest filter pattern.
            HELP
         );
   }

   protected function execute (InputInterface $input, OutputInterface $output): int
   {
      $method = $input->getArgument ('method');
      $path = $input->getArgument ('path');
      $customFilter = $input->getOption ('filter');

      $route = null;
      if ($method && $path) {
         $route = $method . ' ' . $path;
      } elseif ($method) {
         $route = $method;
      }

      $pestBin = $this->root . '/vendor/bin/pest';

      if (!file_exists ($pestBin)) {
         $output->writeln ('<error>Pest not found. Run: composer require --dev pestphp/pest</error>');
         return Command::FAILURE;
      }

      $command = escapeshellarg ($pestBin);
      $args = [];

      if ($customFilter) {
         $args [] = '--filter=' . escapeshellarg ($customFilter);
      } elseif ($route) {
         $filter = $this->routeToFilter ($route);
         $args [] = '--filter=' . escapeshellarg ($filter);
      }

      if ($output->isDecorated ()) {
         $args [] = '--colors=always';
      }

      $fullCommand = $command . ' ' . implode (' ', $args);

      $output->writeln ("<comment>Running:</comment> {$fullCommand}\n");

      passthru ($fullCommand, $exitCode);

      return $exitCode === 0 ? Command::SUCCESS : Command::FAILURE;
   }

   private function routeToFilter (string $route): string
   {
      $route = trim ($route);

      if (preg_match ('/^(GET|POST|PUT|PATCH|DELETE)\s+(.+)$/', $route, $matches)) {
         $method = $matches [1];
         $path = $matches [2];

         return $method . ' ' . $path;
      }

      return $route;
   }
}
