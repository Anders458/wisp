<?php

namespace Wisp\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Wisp\Router;

#[AsCommand (
   name: 'route:cache',
   description: 'Cache all registered routes for production'
)]
class RouteCacheCommand extends Command
{
   public function __construct (
      private Router $router
   )
   {
      parent::__construct ();
   }

   protected function execute (InputInterface $input, OutputInterface $output) : int
   {
      $io = new SymfonyStyle ($input, $output);

      $io->info ('Caching routes...');

      $this->router->warmup ();

      $routeCount = count ($this->router->routes->all ());
      $io->success ("Cached {$routeCount} routes successfully");

      return Command::SUCCESS;
   }
}
