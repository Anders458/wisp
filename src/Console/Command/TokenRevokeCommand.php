<?php

namespace Wisp\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Wisp\Contracts\TokenProviderInterface;

#[AsCommand (
   name: 'token:revoke',
   description: 'Revoke an access or refresh token'
)]
class TokenRevokeCommand extends Command
{
   public function __construct (
      private TokenProviderInterface $provider
   )
   {
      parent::__construct ();
   }

   protected function configure () : void
   {
      $this
         ->addArgument ('token', InputArgument::REQUIRED, 'Access or refresh token to revoke');
   }

   protected function execute (InputInterface $input, OutputInterface $output) : int
   {
      $io = new SymfonyStyle ($input, $output);

      $token = $input->getArgument ('token');

      $revoked = $this->provider->revoke ($token);

      if ($revoked) {
         $io->success ('Token revoked successfully (both access and refresh tokens invalidated)');
         return Command::SUCCESS;
      }

      $io->error ('Token not found or already revoked');
      return Command::FAILURE;
   }
}
