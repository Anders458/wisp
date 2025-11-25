<?php

namespace Wisp\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand (
   name: 'serve',
   description: 'Start a local development server'
)]
class ServeCommand extends Command
{
   public function __construct (
      private string $root
   )
   {
      parent::__construct ();
   }

   protected function configure () : void
   {
      $this
         ->addOption ('port', 'p', InputOption::VALUE_OPTIONAL, 'Port number', 8000)
         ->addOption ('host', null, InputOption::VALUE_OPTIONAL, 'Host address', '127.0.0.1');
   }

   protected function execute (InputInterface $input, OutputInterface $output) : int
   {
      $io = new SymfonyStyle ($input, $output);

      $port = $input->getOption ('port');
      $host = $input->getOption ('host');

      $symfonyBin = trim (shell_exec ('which symfony 2>/dev/null') ?? '');

      if (!empty ($symfonyBin) && is_executable ($symfonyBin)) {
         $io->info ("Starting Symfony server on {$host}:{$port}...");
         $io->newLine ();

         $command = sprintf (
            'symfony local:server:start --port=%d --no-tls --dir=%s',
            $port,
            escapeshellarg ($this->root)
         );

         passthru ($command, $exitCode);

         return $exitCode ?: Command::SUCCESS;
      }

      $io->info ("Starting PHP built-in server on {$host}:{$port}...");
      $io->comment ('Press Ctrl+C to stop the server');
      $io->newLine ();

      $documentRoot = $this->root;
      $router = $this->root . '/index.php';

      if (!file_exists ($router)) {
         $io->error ("Could not find entry point: {$router}");
         return Command::FAILURE;
      }

      $command = sprintf (
         'php -S %s:%d -t %s %s',
         escapeshellarg ($host),
         $port,
         escapeshellarg ($documentRoot),
         escapeshellarg ($router)
      );

      passthru ($command, $exitCode);

      return $exitCode ?: Command::SUCCESS;
   }
}
