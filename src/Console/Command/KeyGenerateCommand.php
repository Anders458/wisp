<?php

namespace Wisp\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Wisp\Contracts\KeyValidatorInterface;

#[AsCommand (
   name: 'key:generate',
   description: 'Generate an API key for authentication'
)]
class KeyGenerateCommand extends Command
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
         ->addArgument ('user_id', InputArgument::REQUIRED, 'User ID')
         ->addArgument ('roles', InputArgument::OPTIONAL, 'User roles (comma-separated)', '')
         ->addArgument ('permissions', InputArgument::OPTIONAL, 'Permissions (comma-separated)', '')
         ->addOption ('ttl', 't', InputOption::VALUE_OPTIONAL, 'Key TTL in seconds (0 = never expires)', 0);
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

      $apiKey = bin2hex (random_bytes (32));

      $this->validator->store (
         $apiKey,
         $userId,
         $roles,
         $permissions,
         $ttl > 0 ? $ttl : null
      );

      $io->success ('API Key generated successfully');
      $io->section ('API Key');
      $io->text ($apiKey);

      if ($ttl > 0) {
         $io->text ('Expires in: ' . $ttl . ' seconds');
      } else {
         $io->text ('Never expires');
      }

      $io->section ('Details');
      $io->text ('User ID: ' . $userId);
      if (!empty ($roles)) {
         $io->text ('Roles: ' . implode (', ', $roles));
      }
      if (!empty ($permissions)) {
         $io->text ('Permissions: ' . implode (', ', $permissions));
      }

      $io->section ('Usage');
      $io->text ('curl -H "X-API-Key: ' . $apiKey . '" http://your-api-url/endpoint');

      return Command::SUCCESS;
   }
}
