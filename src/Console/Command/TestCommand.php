<?php

namespace Wisp\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Wisp\Http\Request;
use Wisp\Testing\FixtureFactory;
use Wisp\Wisp;

#[AsCommand (
   name: 'test',
   description: 'Test HTTP endpoints (API, HTML, etc.)'
)]
class TestCommand extends Command
{
   private Wisp $app;
   private string $root;

   public function __construct (Wisp $app, string $root)
   {
      parent::__construct ();
      $this->app = $app;
      $this->root = $root;
   }

   protected function configure (): void
   {
      $this
         ->addArgument ('method', InputArgument::REQUIRED, 'HTTP method (GET, POST, PUT, DELETE, etc.)')
         ->addArgument ('path', InputArgument::REQUIRED, 'Request path')
         ->addOption ('data', 'd', InputOption::VALUE_REQUIRED, 'JSON data for request body')
         ->addOption ('header', 'H', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Headers (format: "Key: Value")');
   }

   protected function execute (InputInterface $input, OutputInterface $output): int
   {
      $io = new SymfonyStyle ($input, $output);

      $method = strtoupper ($input->getArgument ('method'));
      $path = $input->getArgument ('path');
      $data = $input->getOption ('data');
      $headers = $input->getOption ('header');

      // Auto-generate fixture data if no data provided
      if (!$data && in_array ($method, ['POST', 'PUT', 'PATCH'])) {
         // Try to load fixture mappings from tests/fixtures.php
         $fixtureFile = $this->root . '/tests/fixtures.php';
         if (file_exists ($fixtureFile)) {
            $fixtureMap = require $fixtureFile;
            $routeKey = "$method $path";
            if (isset ($fixtureMap [$routeKey])) {
               $factory = new FixtureFactory ();
               $generated = $factory->create ($fixtureMap [$routeKey]);
               $data = json_encode ($generated);
               $io->note ("Auto-generated fixture data");
            }
         }
      }

      $server = [ 'REQUEST_METHOD' => $method ];

      foreach ($headers as $header) {
         if (strpos ($header, ':') !== false) {
            [ $key, $value ] = explode (':', $header, 2);
            $key = trim ($key);
            $value = trim ($value);
            $serverKey = 'HTTP_' . strtoupper (str_replace ('-', '_', $key));
            $server [$serverKey] = $value;
         }
      }

      $content = null;
      if ($data) {
         $content = $data;
         $server ['CONTENT_TYPE'] = 'application/json';
      }

      $io->section ("Testing {$method} {$path}");

      $request = Request::create ($path, $method, [], [], [], $server, $content);
      $response = $this->app->handleRequest ($request);

      $statusCode = $response->getStatusCode ();
      $statusClass = match (true) {
         $statusCode >= 200 && $statusCode < 300 => 'info',
         $statusCode >= 300 && $statusCode < 400 => 'comment',
         $statusCode >= 400 && $statusCode < 500 => 'error',
         $statusCode >= 500 => 'error',
         default => 'info'
      };

      $io->$statusClass ("Status: {$statusCode}");

      $responseHeaders = $response->headers->all ();
      if (!empty ($responseHeaders)) {
         $io->section ('Headers');
         foreach ($responseHeaders as $key => $values) {
            foreach ($values as $value) {
               $io->writeln ("<comment>{$key}:</comment> {$value}");
            }
         }
      }

      $responseContent = $response->getContent ();
      if ($responseContent) {
         $io->section ('Response');

         $contentType = $response->headers->get ('Content-Type', '');
         if (str_contains ($contentType, 'application/json')) {
            $decoded = json_decode ($responseContent, true);
            if (json_last_error () === JSON_ERROR_NONE) {
               $io->writeln (json_encode ($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            } else {
               $io->writeln ($responseContent);
            }
         } else {
            $io->writeln ($responseContent);
         }
      }

      return $statusCode >= 200 && $statusCode < 400 ? Command::SUCCESS : Command::FAILURE;
   }
}
