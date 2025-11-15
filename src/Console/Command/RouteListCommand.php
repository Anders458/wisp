<?php

namespace Wisp\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Wisp\Router;

#[AsCommand (
   name: 'route:list',
   description: 'List all registered routes'
)]
class RouteListCommand extends Command
{
   public function __construct (
      private Router $router
   )
   {
      parent::__construct ();
   }

   protected function execute (InputInterface $input, OutputInterface $output) : int
   {
      $table = new Table ($output);
      $table->setHeaders (['Name', 'Method', 'Path', 'Controller']);

      foreach ($this->router->routes->all () as $name => $route) {
         $methods = implode ('|', $route->getMethods ());
         $path = $route->getPath ();
         $controller = $route->getDefault ('_controller');

         if (is_array ($controller)) {
            $controller = $controller [0] . '::' . $controller [1];
         } elseif ($controller instanceof \Closure) {
            $controller = 'Closure';
         }

         $table->addRow ([$name, $methods, $path, $controller]);
      }

      $table->render ();

      $output->writeln ('');
      $output->writeln ('<info>Total routes: ' . count ($this->router->routes->all ()) . '</info>');

      return Command::SUCCESS;
   }
}
