<?php

namespace Wisp\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Wisp\Environment\RuntimeInterface;
use Wisp\Routing\Router;

#[AsCommand (
   name: 'route:cache',
   description: 'Cache all registered routes for production using Symfony CompiledUrlMatcherDumper'
)]
class RouteCacheCommand extends Command
{
   public function __construct (
      private Router $router,
      private RuntimeInterface $runtime
   )
   {
      parent::__construct ();
   }

   protected function execute (InputInterface $input, OutputInterface $output) : int
   {
      $io = new SymfonyStyle ($input, $output);

      $io->info ('Caching routes using CompiledUrlMatcherDumper...');

      $this->router->warmup ($this->runtime);

      $routeCount = count ($this->router->routes->all ());
      $io->success ("Cached {$routeCount} routes successfully");

      return Command::SUCCESS;
   }
}
