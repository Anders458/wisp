<?php

namespace Wisp\Console\Command;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Wisp\Security\CacheTokenProvider;

#[AsCommand (
   name: 'token:generate',
   description: 'Generate an access token for testing'
)]
class TokenGenerateCommand extends Command
{
   protected function configure () : void
   {
      $this
         ->addArgument ('user_id', InputArgument::REQUIRED, 'User ID')
         ->addArgument ('roles', InputArgument::OPTIONAL, 'User roles (comma-separated)', '')
         ->addArgument ('permissions', InputArgument::OPTIONAL, 'Permissions (comma-separated)', '')
         ->addOption ('ttl', 't', InputOption::VALUE_OPTIONAL, 'Token TTL in seconds', 3600);
   }

   protected function execute (InputInterface $input, OutputInterface $output) : int
   {
      $io = new SymfonyStyle ($input, $output);

      $userId = $input->getArgument ('user_id');
      $rolesStr = $input->getArgument ('roles');
      $permissionsStr = $input->getArgument ('permissions');
      $ttl = (int) $input->getOption ('ttl');

      $roles = !empty ($rolesStr)
         ? array_map ('trim', explode (',', $rolesStr))
         : [];

      $permissions = !empty ($permissionsStr)
         ? array_map ('trim', explode (',', $permissionsStr))
         : [];

      $cache = container (CacheItemPoolInterface::class);
      $provider = new CacheTokenProvider ($cache, [
         'access' => $ttl,
         'refresh' => $ttl * 7
      ]);

      $tokens = $provider->become ($userId, $roles, $permissions);

      $io->success ('Token generated successfully');
      $io->section ('Access Token');
      $io->text ($tokens ['access_token']);
      $io->text ('Expires in: ' . $tokens ['expires_in'] . ' seconds');

      $io->section ('Refresh Token');
      $io->text ($tokens ['refresh_token']);

      $io->section ('Usage');
      $io->text ('curl -H "Authorization: Bearer ' . $tokens ['access_token'] . '" http://your-api-url/endpoint');

      return Command::SUCCESS;
   }
}
