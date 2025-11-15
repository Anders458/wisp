<?php

namespace Wisp\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Wisp\Container;

#[AsCommand (
   name: 'debug:container',
   description: 'Display all registered services in the container'
)]
class DebugContainerCommand extends Command
{
   protected function execute (InputInterface $input, OutputInterface $output) : int
   {
      $container = Container::instance ();
      $serviceIds = $container->getServiceIds ();

      $table = new Table ($output);
      $table->setHeaders (['Service ID', 'Public']);

      foreach ($serviceIds as $id) {
         $isPublic = $container->has ($id) ? 'Yes' : 'No';
         $table->addRow ([$id, $isPublic]);
      }

      $table->render ();

      $output->writeln ('');
      $output->writeln ('<info>Total services: ' . count ($serviceIds) . '</info>');

      return Command::SUCCESS;
   }
}
