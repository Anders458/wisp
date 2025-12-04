<?php

namespace Wisp\Command;

use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Wisp\Testing\FixtureFactory;
use Wisp\Testing\HasTestFixtures;
use Wisp\Testing\HasTestScenarios;

#[AsCommand (
   name: 'test',
   description: 'Run HTTP tests against routes'
)]
class TestCommand extends Command
{
   public function __construct (
      private KernelInterface $kernel,
      private RouterInterface $router
   )
   {
      parent::__construct ();
   }

   protected function configure (): void
   {
      $this
         ->addArgument ('method', InputArgument::OPTIONAL, 'HTTP method (GET, POST, etc.)')
         ->addArgument ('path', InputArgument::OPTIONAL, 'Route path (e.g., /health)')
         ->addOption ('body', 'b', InputOption::VALUE_REQUIRED, 'JSON request body (overrides auto-generated fixtures)')
         ->addOption ('token', 't', InputOption::VALUE_REQUIRED, 'Bearer token for authentication')
         ->addOption ('header', 'H', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Additional headers (format: "Name: Value")')
         ->addOption ('filter', 'f', InputOption::VALUE_REQUIRED, 'Filter tests by name pattern')
         ->addOption ('only-success', null, InputOption::VALUE_NONE, 'Only run the success case')
         ->addOption ('scenario', 's', InputOption::VALUE_REQUIRED, 'Scenario class for multi-step tests (e.g., App\\Testing\\UserMeScenarios)')
         ->setHelp (<<<'HELP'
Run the full test suite:
  <info>bin/console test</info>

Test a specific endpoint (auto-generates fixtures from DTO):
  <info>bin/console test GET /v1/health</info>
  <info>bin/console test POST /v1/auth/token</info>

Test with scenarios (for endpoints requiring auth setup):
  <info>bin/console test GET /v1/users/@me --scenario 'App\Testing\UserMeScenarios'</info>

Override auto-generated fixtures:
  <info>bin/console test POST /v1/users --body '{"email":"test@example.com"}'</info>

Authenticate requests:
  <info>bin/console test GET /v1/users --token user-token-123</info>

Filter tests by name:
  <info>bin/console test --filter health</info>
HELP);
   }

   protected function execute (InputInterface $input, OutputInterface $output): int
   {
      $io = new SymfonyStyle ($input, $output);
      $method = $input->getArgument ('method');
      $path = $input->getArgument ('path');

      if ($method !== null && $path !== null) {
         return $this->executeEndpointTests ($io, $input, strtoupper ($method), $path);
      }

      return $this->executeTestSuite ($io, $input);
   }

   private function executeEndpointTests (SymfonyStyle $io, InputInterface $input, string $method, string $path): int
   {
      $manualBody = $input->getOption ('body');
      $onlySuccess = $input->getOption ('only-success');
      $scenarioClass = $input->getOption ('scenario');

      // If manual body provided, just run single request
      if ($manualBody !== null) {
         return $this->executeSingleRequest ($io, $input, $method, $path, $manualBody, [], 'Manual request');
      }

      // Auto-detect scenario class if not provided
      if ($scenarioClass === null) {
         $scenarioClass = $this->findScenariosForRoute ($method, $path);
      }

      // Check for scenario class
      if ($scenarioClass !== null) {
         if (!class_exists ($scenarioClass)) {
            $io->error (sprintf ('Scenario class not found: %s', $scenarioClass));
            return Command::FAILURE;
         }

         if (!is_subclass_of ($scenarioClass, HasTestScenarios::class)) {
            $io->error (sprintf ('Class %s must implement HasTestScenarios', $scenarioClass));
            return Command::FAILURE;
         }

         $io->title (sprintf ('Testing %s %s', $method, $path));
         $io->text (sprintf ('Scenarios: <info>%s</info>', $scenarioClass));
         $io->newLine ();

         return $this->executeScenarioTests ($io, $method, $path, $scenarioClass::testScenarios (), $onlySuccess);
      }

      // Try to find DTO for this route
      $dtoClass = $this->findDtoForRoute ($method, $path);

      if ($dtoClass === null) {
         // No DTO, just run without body
         return $this->executeSingleRequest ($io, $input, $method, $path, null, [], 'No payload required');
      }

      $io->title (sprintf ('Testing %s %s', $method, $path));
      $io->text (sprintf ('DTO: <info>%s</info>', $dtoClass));

      // Check if DTO provides its own test fixtures
      if (is_subclass_of ($dtoClass, HasTestFixtures::class)) {
         $io->text ('<fg=cyan>Using fixtures from DTO</>');
         $io->newLine ();

         return $this->executeFixtureTests ($io, $input, $method, $path, $dtoClass::testFixtures (), $onlySuccess);
      }

      $io->text ('<fg=yellow>Auto-generating fixtures from constraints</>');
      $io->newLine ();

      return $this->executeGeneratedTests ($io, $input, $method, $path, $dtoClass, $onlySuccess);
   }

   /**
    * Run tests using fixtures provided by the DTO.
    *
    * @param array<string, array{data: array<string, mixed>, expect: string}> $fixtures
    */
   private function executeFixtureTests (SymfonyStyle $io, InputInterface $input, string $method, string $path, array $fixtures, bool $onlySuccess): int
   {
      $results = [];
      $expectations = [];
      $testNum = 1;

      foreach ($fixtures as $name => $fixture) {
         if ($onlySuccess && $name !== 'valid') {
            continue;
         }

         $io->section (sprintf ('Test %d: %s', $testNum++, $name));
         $this->printFixture ($io, $fixture ['data']);

         $result = $this->executeSingleRequest ($io, $input, $method, $path, json_encode ($fixture ['data']), [], null);
         $results [$name] = $result;
         $expectations [$name] = $fixture ['expect'];
      }

      if ($onlySuccess) {
         return $results ['valid'] ?? Command::FAILURE;
      }

      // Summary
      $io->newLine ();
      $io->section ('Summary');

      $passed = 0;
      $failed = 0;

      foreach ($results as $name => $result) {
         $expect = $expectations [$name];
         $success = $this->checkExpectation ($result, $expect);

         if ($success) {
            $io->writeln (sprintf ('  <fg=green>✓</> %s: passed', $name));
            $passed++;
         } else {
            $expectedStatus = $expect === 'success' ? '2xx' : '4xx';
            $io->writeln (sprintf ('  <fg=red>✗</> %s: failed (expected %s)', $name, $expectedStatus));
            $failed++;
         }
      }

      $io->newLine ();
      $io->writeln (sprintf ('Total: %d passed, %d failed', $passed, $failed));

      return $failed === 0 ? Command::SUCCESS : Command::FAILURE;
   }

   /**
    * Run tests using auto-generated fixtures from constraints.
    */
   private function executeGeneratedTests (SymfonyStyle $io, InputInterface $input, string $method, string $path, string $dtoClass, bool $onlySuccess): int
   {
      $factory = new FixtureFactory ();
      $results = [];

      // Test 1: Valid data (success case)
      $validData = $factory->create ($dtoClass);
      $io->section ('Test 1: Valid data');
      $this->printFixture ($io, $validData);
      $results ['valid'] = $this->executeSingleRequest ($io, $input, $method, $path, json_encode ($validData), [], null);

      if ($onlySuccess) {
         return $results ['valid'];
      }

      // Generate failure test cases based on DTO constraints
      $failureCases = $this->generateFailureCases ($dtoClass, $factory);
      $testNum = 2;

      foreach ($failureCases as $caseName => $caseData) {
         $io->section (sprintf ('Test %d: %s', $testNum++, $caseName));
         $this->printFixture ($io, $caseData);
         $results [$caseName] = $this->executeSingleRequest ($io, $input, $method, $path, json_encode ($caseData), [], null);
      }

      // Summary
      $io->newLine ();
      $io->section ('Summary');

      $passed = 0;
      $failed = 0;

      foreach ($results as $name => $result) {
         if ($name === 'valid') {
            if ($result === Command::SUCCESS) {
               $io->writeln (sprintf ('  <fg=green>✓</> Valid data: passed'));
               $passed++;
            } else {
               $io->writeln (sprintf ('  <fg=red>✗</> Valid data: failed (expected 2xx)'));
               $failed++;
            }
         } else {
            if ($result === Command::FAILURE) {
               $io->writeln (sprintf ('  <fg=green>✓</> %s: passed (returned 4xx as expected)', $name));
               $passed++;
            } else {
               $io->writeln (sprintf ('  <fg=red>✗</> %s: failed (expected 4xx)', $name));
               $failed++;
            }
         }
      }

      $io->newLine ();
      $io->writeln (sprintf ('Total: %d passed, %d failed', $passed, $failed));

      return $failed === 0 ? Command::SUCCESS : Command::FAILURE;
   }

   private function checkExpectation (int $result, string $expect): bool
   {
      return match ($expect) {
         'success' => $result === Command::SUCCESS,
         'validation_error', 'auth_error', 'error' => $result === Command::FAILURE,
         default => false
      };
   }

   /**
    * Run tests using multi-step scenarios.
    */
   private function executeScenarioTests (SymfonyStyle $io, string $method, string $path, array $scenarios, bool $onlySuccess): int
   {
      $results = [];
      $expectations = [];
      $testNum = 1;

      foreach ($scenarios as $name => $scenario) {
         if ($onlySuccess && !str_contains ($name, 'with_token') && !str_contains ($name, 'with_session')) {
            continue;
         }

         $io->section (sprintf ('Test %d: %s', $testNum++, $name));

         $variables = [];

         // Run setup steps
         if (isset ($scenario ['setup'])) {
            $io->writeln ('<fg=yellow>Setup:</>');

            foreach ($scenario ['setup'] as $step) {
               $io->writeln (sprintf ('  %s %s', $step ['method'], $step ['path']));

               $body = isset ($step ['body']) ? json_encode ($step ['body']) : null;
               $headers = $step ['headers'] ?? [];

               // Substitute variables in headers
               foreach ($headers as $key => $value) {
                  $headers [$key] = $this->substituteVariables ($value, $variables);
               }

               $result = $this->executeRequest ($step ['method'], $step ['path'], $body, [], $headers);

               if ($result ['response'] === null) {
                  $io->writeln ('  <fg=red>Setup step failed</>');
                  $results [$name] = Command::FAILURE;
                  $expectations [$name] = $scenario ['expect'];
                  continue 2;
               }

               // Extract values from response
               if (isset ($step ['extract'])) {
                  $content = $result ['response']->getContent ();
                  $decoded = json_decode ($content, true) ?? [];

                  foreach ($step ['extract'] as $varName => $extractPath) {
                     if (str_starts_with ($extractPath, '@cookie:')) {
                        // Extract from cookie
                        $cookieName = substr ($extractPath, 8);
                        $cookies = $result ['response']->headers->getCookies ();

                        foreach ($cookies as $cookie) {
                           if ($cookie->getName () === $cookieName) {
                              $variables [$varName] = $cookie->getValue ();
                              $io->writeln (sprintf ('  Extracted %s from cookie', $varName));
                              break;
                           }
                        }
                     } else {
                        // Extract from response body
                        $value = $this->extractFromPath ($decoded, $extractPath);

                        if ($value !== null) {
                           $variables [$varName] = $value;
                           $io->writeln (sprintf ('  Extracted %s = %s', $varName, substr ((string) $value, 0, 30) . '...'));
                        }
                     }
                  }
               }
            }

            $io->newLine ();
         }

         // Build main request
         $requestConfig = $scenario ['request'] ?? [];
         $headers = $requestConfig ['headers'] ?? [];
         $body = isset ($requestConfig ['body']) ? json_encode ($requestConfig ['body']) : null;

         // Substitute variables
         foreach ($headers as $key => $value) {
            $headers [$key] = $this->substituteVariables ($value, $variables);
         }

         // Display request
         $io->writeln ('<fg=cyan>Request Headers:</>');
         $io->writeln ('  Accept: application/json');
         $io->writeln ('  Content-Type: application/json');

         foreach ($headers as $hName => $hValue) {
            if (strtolower ($hName) === 'authorization' && str_starts_with ($hValue, 'Bearer ')) {
               $io->writeln (sprintf ('  %s: Bearer %s...', $hName, substr ($hValue, 7, 20)));
            } else {
               $io->writeln (sprintf ('  %s: %s', $hName, $hValue));
            }
         }

         $io->newLine ();

         // Execute main request
         $result = $this->executeRequest ($method, $path, $body, [], $headers);
         $statusCode = $result ['status'];
         $response = $result ['response'];

         $statusColor = match (true) {
            $statusCode >= 200 && $statusCode < 300 => 'green',
            $statusCode >= 300 && $statusCode < 400 => 'yellow',
            $statusCode >= 400 && $statusCode < 500 => 'red',
            default => 'red'
         };

         $io->writeln (sprintf ('<fg=%s>HTTP %d</>', $statusColor, $statusCode));
         $io->newLine ();

         if ($response !== null) {
            $io->writeln ('<fg=cyan>Response Headers:</>');

            foreach ($response->headers->all () as $hName => $values) {
               foreach ($values as $value) {
                  $io->writeln (sprintf ('  %s: %s', $hName, $value));
               }
            }

            $io->newLine ();
            $io->writeln ('<fg=cyan>Response Body:</>');
            $content = $response->getContent ();

            if ($content !== false && $content !== '') {
               $decoded = json_decode ($content, true);

               if (json_last_error () === JSON_ERROR_NONE) {
                  $io->writeln (json_encode ($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
               } else {
                  $io->writeln ($content);
               }
            }
         }

         $results [$name] = $statusCode >= 200 && $statusCode < 400 ? Command::SUCCESS : Command::FAILURE;
         $expectations [$name] = $scenario ['expect'];
      }

      // Summary
      $io->newLine ();
      $io->section ('Summary');

      $passed = 0;
      $failed = 0;

      foreach ($results as $name => $result) {
         $expect = $expectations [$name];
         $success = $this->checkExpectation ($result, $expect);

         if ($success) {
            $io->writeln (sprintf ('  <fg=green>✓</> %s: passed', $name));
            $passed++;
         } else {
            $expectedStatus = $expect === 'success' ? '2xx' : '4xx';
            $io->writeln (sprintf ('  <fg=red>✗</> %s: failed (expected %s)', $name, $expectedStatus));
            $failed++;
         }
      }

      $io->newLine ();
      $io->writeln (sprintf ('Total: %d passed, %d failed', $passed, $failed));

      return $failed === 0 ? Command::SUCCESS : Command::FAILURE;
   }

   private function substituteVariables (string $value, array $variables): string
   {
      foreach ($variables as $name => $varValue) {
         $value = str_replace ('{{' . $name . '}}', $varValue, $value);
      }

      return $value;
   }

   private function extractFromPath (array $data, string $path): mixed
   {
      $parts = explode ('.', $path);

      foreach ($parts as $part) {
         if (!is_array ($data) || !isset ($data [$part])) {
            return null;
         }

         $data = $data [$part];
      }

      return $data;
   }

   private function printFixture (SymfonyStyle $io, array $data): void
   {
      $io->writeln ('<fg=cyan>Fixture:</> ' . json_encode ($data, JSON_UNESCAPED_SLASHES));
      $io->newLine ();
   }

   /**
    * @param array<string, string> $cookies
    * @param array<string, string> $extraHeaders
    * @return array{status: int, response: Response|null}
    */
   private function executeRequest (string $method, string $path, ?string $body, array $cookies = [], array $extraHeaders = []): array
   {
      $server = [
         'HTTP_ACCEPT' => 'application/json',
         'CONTENT_TYPE' => 'application/json'
      ];

      foreach ($extraHeaders as $name => $value) {
         $serverName = 'HTTP_' . strtoupper (str_replace ('-', '_', $name));
         $server [$serverName] = $value;
      }

      $request = Request::create (
         $path,
         $method,
         [],
         $cookies,
         [],
         $server,
         $body
      );

      try {
         $response = $this->kernel->handle ($request);
         $statusCode = $response->getStatusCode ();
         $this->kernel->terminate ($request, $response);

         return [ 'status' => $statusCode, 'response' => $response ];
      } catch (\Throwable) {
         return [ 'status' => 500, 'response' => null ];
      }
   }

   /**
    * @param array<string, string> $extraHeaders
    */
   private function executeSingleRequest (SymfonyStyle $io, ?InputInterface $input, string $method, string $path, ?string $body, array $extraHeaders, ?string $label): int
   {
      $token = $input?->getOption ('token');
      $headers = $input?->getOption ('header') ?? [];

      if ($label !== null) {
         $io->section (sprintf ('%s %s - %s', $method, $path, $label));
      }

      // Merge CLI headers with extra headers
      $allHeaders = $extraHeaders;

      if ($token !== null) {
         $allHeaders ['Authorization'] = 'Bearer ' . $token;
      }

      foreach ($headers as $header) {
         if (str_contains ($header, ':')) {
            [ $name, $value ] = explode (':', $header, 2);
            $allHeaders [trim ($name)] = trim ($value);
         }
      }

      // Display request headers
      $io->writeln ('<fg=cyan>Request Headers:</>');
      $io->writeln ('  Accept: application/json');
      $io->writeln ('  Content-Type: application/json');

      foreach ($allHeaders as $name => $value) {
         if (strtolower ($name) === 'authorization' && str_starts_with ($value, 'Bearer ')) {
            $io->writeln (sprintf ('  %s: Bearer %s...', $name, substr ($value, 7, 20)));
         } else {
            $io->writeln (sprintf ('  %s: %s', $name, $value));
         }
      }

      $io->newLine ();

      $result = $this->executeRequest ($method, $path, $body, [], $allHeaders);
      $statusCode = $result ['status'];
      $response = $result ['response'];

      $statusColor = match (true) {
         $statusCode >= 200 && $statusCode < 300 => 'green',
         $statusCode >= 300 && $statusCode < 400 => 'yellow',
         $statusCode >= 400 && $statusCode < 500 => 'red',
         default => 'red'
      };

      $io->writeln (sprintf (
         '<fg=%s>HTTP %d</>',
         $statusColor,
         $statusCode
      ));

      $io->newLine ();

      if ($response !== null) {
         // Display response headers
         $io->writeln ('<fg=cyan>Response Headers:</>');

         foreach ($response->headers->all () as $name => $values) {
            foreach ($values as $value) {
               $io->writeln (sprintf ('  %s: %s', $name, $value));
            }
         }

         $io->newLine ();

         // Display response body
         $io->writeln ('<fg=cyan>Response Body:</>');
         $content = $response->getContent ();

         if ($content !== false && $content !== '') {
            $decoded = json_decode ($content, true);

            if (json_last_error () === JSON_ERROR_NONE) {
               $io->writeln (json_encode ($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            } else {
               $io->writeln ($content);
            }
         }
      }

      return $statusCode >= 200 && $statusCode < 400 ? Command::SUCCESS : Command::FAILURE;
   }

   /**
    * Find the DTO class used by MapRequestPayload for a given route.
    */
   private function findDtoForRoute (string $method, string $path): ?string
   {
      $routes = $this->router->getRouteCollection ();

      foreach ($routes as $route) {
         if (!in_array ($method, $route->getMethods (), true)) {
            continue;
         }

         // Check if path matches (handle path parameters)
         $pattern = preg_replace ('/\{[^}]+\}/', '[^/]+', $route->getPath ());
         $pattern = '#^' . $pattern . '$#';

         if (!preg_match ($pattern, $path)) {
            continue;
         }

         $controller = $route->getDefault ('_controller');

         if ($controller === null) {
            continue;
         }

         // Parse controller string (e.g., "App\Controller\AuthController::token")
         if (str_contains ($controller, '::')) {
            [ $class, $methodName ] = explode ('::', $controller);
         } else {
            // Invokable controller
            $class = $controller;
            $methodName = '__invoke';
         }

         if (!class_exists ($class)) {
            continue;
         }

         $reflection = new ReflectionClass ($class);

         if (!$reflection->hasMethod ($methodName)) {
            continue;
         }

         $reflectionMethod = $reflection->getMethod ($methodName);

         foreach ($reflectionMethod->getParameters () as $parameter) {
            $attributes = $parameter->getAttributes (MapRequestPayload::class);

            if (!empty ($attributes)) {
               $paramType = $parameter->getType ();

               if ($paramType !== null && !$paramType->isBuiltin ()) {
                  return $paramType->getName ();
               }
            }
         }
      }

      return null;
   }

   /**
    * Generate failure test cases based on DTO validation constraints.
    *
    * @return array<string, array<string, mixed>>
    */
   private function generateFailureCases (string $dtoClass, FixtureFactory $factory): array
   {
      $cases = [];
      $reflection = new ReflectionClass ($dtoClass);

      foreach ($reflection->getProperties (\ReflectionProperty::IS_PUBLIC) as $property) {
         $name = $property->getName ();
         $type = $property->getType ()?->getName ();
         $attributes = $property->getAttributes ();

         foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance ();

            // Generate invalid case for Email constraint
            if ($instance instanceof Assert\Email) {
               $cases ["Invalid {$name} (bad email)"] = $factory->create ($dtoClass, [
                  $name => 'not-an-email'
               ]);
               break;
            }

            // Generate invalid case for NotBlank constraint
            if ($instance instanceof Assert\NotBlank) {
               $cases ["Empty {$name}"] = $factory->create ($dtoClass, [
                  $name => ''
               ]);
               break;
            }

            // Generate invalid case for Length constraint
            if ($instance instanceof Assert\Length) {
               if ($instance->min !== null && $instance->min > 1) {
                  $cases ["Too short {$name}"] = $factory->create ($dtoClass, [
                     $name => 'a'
                  ]);
               }
               break;
            }

            // Generate invalid case for Range constraint
            if ($instance instanceof Assert\Range) {
               if ($instance->min !== null) {
                  $invalidValue = $type === 'int' ? (int) $instance->min - 10 : (float) $instance->min - 10;
                  $cases ["Below min {$name}"] = $factory->create ($dtoClass, [
                     $name => $invalidValue
                  ]);
               }
               break;
            }

            // Generate invalid case for Positive constraint
            if ($instance instanceof Assert\Positive) {
               $cases ["Negative {$name}"] = $factory->create ($dtoClass, [
                  $name => $type === 'int' ? -1 : -1.0
               ]);
               break;
            }
         }
      }

      return $cases;
   }

   /**
    * Find a scenarios class for a route by naming convention.
    *
    * Looks for classes like UserMeScenarios for UserController::me
    * in the App\Testing namespace.
    */
   private function findScenariosForRoute (string $method, string $path): ?string
   {
      $routes = $this->router->getRouteCollection ();

      foreach ($routes as $route) {
         if (!in_array ($method, $route->getMethods (), true)) {
            continue;
         }

         // Check if path matches
         $pattern = preg_replace ('/\{[^}]+\}/', '[^/]+', $route->getPath ());
         $pattern = '#^' . $pattern . '$#';

         if (!preg_match ($pattern, $path)) {
            continue;
         }

         $controller = $route->getDefault ('_controller');

         if ($controller === null) {
            continue;
         }

         // Parse controller string (e.g., "App\Controller\UserController::me")
         if (str_contains ($controller, '::')) {
            [ $class, $methodName ] = explode ('::', $controller);
         } else {
            continue;
         }

         // Extract controller name without "Controller" suffix
         $parts = explode ('\\', $class);
         $controllerName = end ($parts);
         $controllerName = preg_replace ('/Controller$/', '', $controllerName);

         // Build scenario class name (e.g., UserMeScenarios)
         $scenarioClassName = ucfirst ($controllerName) . ucfirst ($methodName) . 'Scenarios';

         // Look for the class in App\Testing namespace
         $scenarioClass = 'App\\Testing\\' . $scenarioClassName;

         if (class_exists ($scenarioClass) && is_subclass_of ($scenarioClass, HasTestScenarios::class)) {
            return $scenarioClass;
         }
      }

      return null;
   }

   private function executeTestSuite (SymfonyStyle $io, InputInterface $input): int
   {
      $filter = $input->getOption ('filter');
      $projectDir = $this->kernel->getProjectDir ();

      $phpunit = $projectDir . '/vendor/bin/phpunit';

      if (!file_exists ($phpunit)) {
         $phpunit = $projectDir . '/bin/phpunit';
      }

      if (!file_exists ($phpunit)) {
         $io->error ('PHPUnit not found. Install it with: composer require --dev phpunit/phpunit');

         return Command::FAILURE;
      }

      $command = [ $phpunit ];

      if ($filter !== null) {
         $command [] = '--filter';
         $command [] = $filter;
      }

      $io->section ('Running test suite');

      $process = proc_open (
         $command,
         [
            0 => STDIN,
            1 => STDOUT,
            2 => STDERR
         ],
         $pipes,
         $projectDir
      );

      if (is_resource ($process)) {
         $exitCode = proc_close ($process);

         return $exitCode === 0 ? Command::SUCCESS : Command::FAILURE;
      }

      return Command::FAILURE;
   }
}
