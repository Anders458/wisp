<?php

namespace Wisp\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Extracts log entries by request ID.
 *
 * Usage: php bin/console debug:request abc123
 *        php bin/console debug:request abc123 --log-file=var/log/prod.log
 */
#[AsCommand (
   name: 'debug:request',
   description: 'Display log entries for a specific request ID'
)]
class DebugRequestCommand extends Command
{
   protected function configure (): void
   {
      $this
         ->addArgument ('request_id', InputArgument::REQUIRED, 'The request ID to search for')
         ->addOption ('log-file', 'l', InputOption::VALUE_REQUIRED, 'Path to log file', 'var/log/dev.log');
   }

   protected function execute (InputInterface $input, OutputInterface $output): int
   {
      $io = new SymfonyStyle ($input, $output);
      $requestId = $input->getArgument ('request_id');
      $logFile = $input->getOption ('log-file');

      if (!file_exists ($logFile)) {
         $io->error ("Log file not found: {$logFile}");
         return Command::FAILURE;
      }

      $matches = $this->findEntries ($logFile, $requestId);

      if (empty ($matches)) {
         $io->warning ("No log entries found for request_id: {$requestId}");
         return Command::SUCCESS;
      }

      $io->title ("Request: {$requestId}");
      $io->text (sprintf ('Found %d log entries', count ($matches)));
      $io->newLine ();

      foreach ($matches as $entry) {
         $this->displayEntry ($io, $entry);
      }

      return Command::SUCCESS;
   }

   private function findEntries (string $logFile, string $requestId): array
   {
      $matches = [];
      $handle = fopen ($logFile, 'r');

      if (!$handle) {
         return [];
      }

      while (($line = fgets ($handle)) !== false) {
         $entry = $this->parseLine ($line);

         if ($entry && ($entry ['context'] ['request_id'] ?? null) === $requestId) {
            $matches [] = $entry;
         }
      }

      fclose ($handle);

      return $matches;
   }

   private function parseLine (string $line): ?array
   {
      // Match: [2024-12-07T10:00:00.123456+00:00] channel.LEVEL: message {json} []
      // Also:  [2024-12-07 10:00:00] channel.LEVEL: message {json} []
      $pattern = '/^\[([^\]]+)\] (\w+)\.(\w+): (.+?) (\{.+\}) \[\]$/';

      if (!preg_match ($pattern, trim ($line), $matches)) {
         return null;
      }

      $context = json_decode ($matches [5], true);

      if (!is_array ($context)) {
         return null;
      }

      // Normalize timestamp for display
      $timestamp = $matches [1];
      if (str_contains ($timestamp, 'T')) {
         $dt = new \DateTime ($timestamp);
         $timestamp = $dt->format ('Y-m-d H:i:s');
      }

      return [
         'timestamp' => $timestamp,
         'channel' => $matches [2],
         'level' => $matches [3],
         'message' => $matches [4],
         'context' => $context
      ];
   }

   private function displayEntry (SymfonyStyle $io, array $entry): void
   {
      $levelColors = [
         'DEBUG' => 'fg=gray',
         'INFO' => 'fg=green',
         'WARNING' => 'fg=yellow',
         'ERROR' => 'fg=red',
         'CRITICAL' => 'fg=red;options=bold'
      ];

      $color = $levelColors [$entry ['level']] ?? 'fg=default';

      $io->writeln (sprintf (
         '<fg=cyan>%s</> <%s>[%s]</> %s',
         $entry ['timestamp'],
         $color,
         $entry ['level'],
         $entry ['message']
      ));

      // Display context without request_id (already shown in title)
      $context = $entry ['context'];
      unset ($context ['request_id']);

      if (!empty ($context)) {
         foreach ($context as $key => $value) {
            if (is_array ($value)) {
               $value = json_encode ($value, JSON_UNESCAPED_SLASHES);
            }
            $io->writeln (sprintf ('  <fg=gray>%s</>: %s', $key, $value));
         }
      }

      $io->newLine ();
   }
}
