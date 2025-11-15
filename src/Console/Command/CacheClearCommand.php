<?php

namespace Wisp\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand (
   name: 'cache:clear',
   description: 'Clear the application cache'
)]
class CacheClearCommand extends Command
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
         ->addOption ('routes', null, InputOption::VALUE_NONE, 'Clear routes cache only')
         ->addOption ('all', null, InputOption::VALUE_NONE, 'Clear all cache');
   }

   protected function execute (InputInterface $input, OutputInterface $output) : int
   {
      $io = new SymfonyStyle ($input, $output);

      $cacheDir = $this->root . '/var/cache';
      $cleared = [];

      if ($input->getOption ('routes') || $input->getOption ('all')) {
         $routesCache = $cacheDir . '/routes/routes.cache';
         if (file_exists ($routesCache)) {
            unlink ($routesCache);
            $cleared [] = 'Routes cache';
         }
      }

      if ($input->getOption ('all') || (!$input->getOption ('routes'))) {
         // Clear all cache
         if (is_dir ($cacheDir)) {
            $this->deleteDirectory ($cacheDir);
            $cleared [] = 'All cache';
         }
      }

      if (empty ($cleared)) {
         $io->info ('No cache to clear');
      } else {
         $io->success ('Cleared: ' . implode (', ', $cleared));
      }

      return Command::SUCCESS;
   }

   private function deleteDirectory (string $dir) : void
   {
      if (!is_dir ($dir)) {
         return;
      }

      $files = array_diff (scandir ($dir), ['.', '..']);

      foreach ($files as $file) {
         $path = $dir . '/' . $file;

         if (is_dir ($path)) {
            $this->deleteDirectory ($path);
         } else {
            unlink ($path);
         }
      }

      rmdir ($dir);
   }
}
