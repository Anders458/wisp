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
use Wisp\Wisp;

#[AsCommand(
   name: 'test',
   description: 'Test an API endpoint'
)]
class TestCommand extends Command
{
   public function __construct(
      private Wisp $app
   ) {
      parent::__construct();
   }

   protected function configure(): void
   {
      $this
         ->addArgument('method', InputArgument::REQUIRED, 'HTTP method (GET, POST, PUT, DELETE, etc.)')
         ->addArgument('path', InputArgument::REQUIRED, 'Request path (e.g., /v1/health-check)')
         ->addOption('data', 'd', InputOption::VALUE_REQUIRED, 'JSON data for request body')
         ->addOption('header', 'H', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'HTTP header (e.g., "Authorization: Bearer token")')
         ->setHelp(<<<'HELP'
Test API endpoints directly without a running web server.

Examples:
  php bin/console test GET /v1/health-check
  php bin/console test POST /v1/gateway/login -d '{"email":"test@example.com","password":"password"}'
  php bin/console test GET /v1/users/@me -H "Authorization: Bearer YOUR_TOKEN"
HELP
         );
   }

   protected function execute(InputInterface $input, OutputInterface $output): int
   {
      $io = new SymfonyStyle($input, $output);

      $method = strtoupper($input->getArgument('method'));
      $path = $input->getArgument('path');
      $data = $input->getOption('data');
      $headers = $input->getOption('header');

      // Build request
      $server = [
         'REQUEST_METHOD' => $method,
         'REQUEST_URI' => $path,
         'SERVER_PROTOCOL' => 'HTTP/1.1',
         'HTTP_HOST' => 'localhost',
         'REMOTE_ADDR' => '127.0.0.1',
      ];

      // Parse custom headers
      foreach ($headers as $header) {
         if (preg_match('/^([^:]+):\s*(.+)$/', $header, $matches)) {
            $headerName = $matches[1];
            $headerValue = $matches[2];

            // Convert to $_SERVER format (e.g., Authorization -> HTTP_AUTHORIZATION)
            $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $headerName));
            $server[$serverKey] = $headerValue;
         }
      }

      $query = [];
      $post = [];
      $content = null;

      // Parse query string from path
      if (str_contains($path, '?')) {
         [$path, $queryString] = explode('?', $path, 2);
         parse_str($queryString, $query);
         $server['REQUEST_URI'] = $path;
         $server['QUERY_STRING'] = $queryString;
      }

      // Parse request data
      if ($data) {
         $content = $data;
         $decoded = json_decode($data, true);

         if (json_last_error() === JSON_ERROR_NONE) {
            $post = $decoded;
            $server['CONTENT_TYPE'] = 'application/json';
         } else {
            parse_str($data, $post);
            $server['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
         }
      }

      // Create request
      // Symfony's Request::create signature: create(uri, method, parameters, cookies, files, server, content)
      $request = Request::create(
         $path,
         $method,
         $method === 'GET' ? $query : $post,
         [],  // cookies
         [],  // files
         $server,
         $content
      );

      // Display request info
      $io->section('Request');
      $io->text("<fg=cyan>$method</> <fg=white>$path</>");

      if (!empty($headers)) {
         $io->text('');
         $io->text('<fg=yellow>Headers:</>');
         foreach ($headers as $header) {
            $io->text("  $header");
         }
      }

      if ($data) {
         $io->text('');
         $io->text('<fg=yellow>Body:</>');
         $io->text('  ' . $data);
      }

      // Execute request
      try {
         // Handle the request
         $response = $this->app->handleRequest($request);

         // Display response
         $io->section('Response');

         $statusCode = $response->getStatusCode();
         $statusColor = $statusCode >= 200 && $statusCode < 300 ? 'green' : 'red';
         $io->text("<fg=$statusColor>Status:</> $statusCode");

         // Show headers
         $io->text('');
         $io->text('<fg=yellow>Headers:</>');
         foreach ($response->headers->all() as $name => $values) {
            foreach ($values as $value) {
               $io->text("  $name: $value");
            }
         }

         // Show body
         $body = $response->getContent();

         if ($body) {
            $io->text('');
            $io->text('<fg=yellow>Body:</>');

            // Try to pretty-print JSON
            $decoded = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE) {
               $formatted = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
               $io->text($formatted);
            } else {
               $io->text($body);
            }
         }

         return $statusCode >= 200 && $statusCode < 300 ? Command::SUCCESS : Command::FAILURE;

      } catch (\Throwable $e) {
         $io->error([
            'Request failed:',
            $e->getMessage(),
            '',
            'Stack trace:',
            $e->getTraceAsString()
         ]);

         return Command::FAILURE;
      }
   }
}
