<?php

namespace Wisp\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Wisp\Contracts\KeyValidatorInterface;

#[AsCommand (
   name: 'key:revoke',
   description: 'Revoke an API key'
)]
class KeyRevokeCommand extends Command
{
   public function __construct (
      private KeyValidatorInterface $validator
   )
   {
      parent::__construct ();
   }

   protected function configure () : void
   {
      $this
         ->addArgument ('key', InputArgument::REQUIRED, 'API key to revoke');
   }

   protected function execute (InputInterface $input, OutputInterface $output) : int
   {
      $io = new SymfonyStyle ($input, $output);

      $key = $input->getArgument ('key');

      $revoked = $this->validator->revoke ($key);

      if ($revoked) {
         $io->success ('API key revoked successfully');
         return Command::SUCCESS;
      }

      $io->error ('API key not found or already revoked');
      return Command::FAILURE;
   }
}
