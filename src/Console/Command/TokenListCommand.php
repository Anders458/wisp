<?php

namespace Wisp\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Wisp\Contracts\TokenProviderInterface;

#[AsCommand (
   name: 'token:list',
   description: 'List all active access tokens'
)]
class TokenListCommand extends Command
{
   public function __construct (
      private TokenProviderInterface $provider
   )
   {
      parent::__construct ();
   }

   protected function execute (InputInterface $input, OutputInterface $output) : int
   {
      $tokens = $this->provider->list ();

      if (empty ($tokens)) {
         $output->writeln ('<info>No active tokens found</info>');
         return Command::SUCCESS;
      }

      $table = new Table ($output);
      $table->setHeaders (['User ID', 'Roles', 'Permissions', 'Created']);

      foreach ($tokens as $token) {
         $table->addRow ([
            $token ['user_id'],
            implode (', ', $token ['roles']),
            implode (', ', $token ['permissions']),
            date ('Y-m-d H:i:s', $token ['created_at'])
         ]);
      }

      $table->render ();

      $output->writeln ('');
      $output->writeln ('<info>Total active tokens: ' . count ($tokens) . '</info>');

      return Command::SUCCESS;
   }
}
