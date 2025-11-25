<?php

namespace Wisp\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Wisp\Contracts\KeyValidatorInterface;

#[AsCommand (
   name: 'key:list',
   description: 'List all active API keys'
)]
class KeyListCommand extends Command
{
   public function __construct (
      private KeyValidatorInterface $validator
   )
   {
      parent::__construct ();
   }

   protected function execute (InputInterface $input, OutputInterface $output) : int
   {
      $keys = $this->validator->list ();

      if (empty ($keys)) {
         $output->writeln ('<info>No API keys found</info>');
         return Command::SUCCESS;
      }

      $table = new Table ($output);
      $table->setHeaders (['Key Hash', 'User ID', 'Roles', 'Permissions', 'Created']);

      foreach ($keys as $key) {
         $table->addRow ([
            substr ($key ['key_hash'], 0, 16) . '...',
            $key ['user_id'],
            implode (', ', $key ['roles']),
            implode (', ', $key ['permissions']),
            date ('Y-m-d H:i:s', $key ['created_at'])
         ]);
      }

      $table->render ();

      $output->writeln ('');
      $output->writeln ('<info>Total API keys: ' . count ($keys) . '</info>');

      return Command::SUCCESS;
   }
}
