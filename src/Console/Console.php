<?php

namespace Wisp\Console;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Wisp\Wisp;

class Console
{
   private Application $application;
   private Wisp $wisp;

   public function __construct (Wisp $wisp, string $name = 'Wisp Console', string $version = '1.0.0')
   {
      $this->wisp = $wisp;
      $this->application = new Application ($name, $version);
   }

   public function discoverCommands (string ...$namespaces) : self
   {
      $commands = [];

      foreach ($namespaces as $namespace) {
         $commands = array_merge ($commands, $this->findCommandsInNamespace ($namespace));
      }

      foreach ($commands as $command) {
         $this->application->add ($command);
      }

      return $this;
   }

   public function registerFrameworkCommands () : self
   {
      $container = \Wisp\Container::instance ();
      $cache = $container->get (\Psr\Cache\CacheItemPoolInterface::class);
      $keyValidator = $container->get (\Wisp\Contracts\KeyValidatorInterface::class);
      $tokenProvider = $container->get (\Wisp\Contracts\TokenProviderInterface::class);
      $runtime = $container->get (\Wisp\Environment\RuntimeInterface::class);

      $frameworkCommands = [
         new \Wisp\Console\Command\CacheClearCommand (getcwd ()),
         new \Wisp\Console\Command\DebugContainerCommand (),
         new \Wisp\Console\Command\KeyGenerateCommand ($keyValidator),
         new \Wisp\Console\Command\KeyListCommand ($keyValidator),
         new \Wisp\Console\Command\KeyRevokeCommand ($keyValidator),
         new \Wisp\Console\Command\RouteCacheCommand ($this->wisp->router, $runtime),
         new \Wisp\Console\Command\RouteListCommand ($this->wisp->router),
         new \Wisp\Console\Command\ServeCommand (getcwd ()),
         new \Wisp\Console\Command\TestCommand ($this->wisp, getcwd ()),
         new \Wisp\Console\Command\TestRunCommand (getcwd ()),
         new \Wisp\Console\Command\TokenGenerateCommand (),
         new \Wisp\Console\Command\TokenListCommand ($tokenProvider),
         new \Wisp\Console\Command\TokenRevokeCommand ($tokenProvider),
      ];

      foreach ($frameworkCommands as $command) {
         $this->application->add ($command);
      }

      return $this;
   }

   public function run () : int
   {
      return $this->application->run ();
   }

   private function findCommandsInNamespace (string $namespace) : array
   {
      $commands = [];
      $namespacePath = str_replace ('\\', '/', $namespace);

      foreach (spl_autoload_functions () as $autoloader) {
         if (!is_array ($autoloader)) {
            continue;
         }

         $loader = $autoloader [0] ?? null;

         if (!$loader || !method_exists ($loader, 'getPrefixesPsr4')) {
            continue;
         }

         $prefixes = $loader->getPrefixesPsr4 ();

         foreach ($prefixes as $prefix => $paths) {
            if (!str_starts_with ($namespace, rtrim ($prefix, '\\'))) {
               continue;
            }

            foreach ((array) $paths as $path) {
               $subPath = substr ($namespacePath, strlen (str_replace ('\\', '/', rtrim ($prefix, '\\'))));
               $fullPath = rtrim ($path, '/') . '/' . ltrim ($subPath, '/');

               if (is_dir ($fullPath)) {
                  $commands = array_merge ($commands, $this->scanDirectory ($fullPath, $namespace));
               }
            }
         }
      }

      return $commands;
   }

   private function scanDirectory (string $directory, string $namespace) : array
   {
      $commands = [];

      if (!is_dir ($directory)) {
         return $commands;
      }

      $files = scandir ($directory);

      foreach ($files as $file) {
         if ($file === '.' || $file === '..') {
            continue;
         }

         $path = $directory . '/' . $file;

         if (is_dir ($path)) {
            $subNamespace = $namespace . '\\' . $file;
            $commands = array_merge ($commands, $this->scanDirectory ($path, $subNamespace));
            continue;
         }

         if (!str_ends_with ($file, '.php')) {
            continue;
         }

         $className = $namespace . '\\' . substr ($file, 0, -4);

         if (!class_exists ($className)) {
            continue;
         }

         if (!is_subclass_of ($className, Command::class)) {
            continue;
         }

         $reflection = new \ReflectionClass ($className);

         if ($reflection->isAbstract ()) {
            continue;
         }

         $constructor = $reflection->getConstructor ();

         if (!$constructor) {
            $commands [] = new $className ();
            continue;
         }

         $params = [];

         foreach ($constructor->getParameters () as $param) {
            $type = $param->getType ();

            if (!$type || $type->isBuiltin ()) {
               continue 2;
            }

            $typeName = $type instanceof \ReflectionNamedType ? $type->getName () : null;

            if ($typeName === Wisp::class) {
               $params [] = $this->wisp;
            } elseif ($typeName === 'Wisp\Router') {
               $params [] = $this->wisp->router;
            } elseif ($param->getName () === 'root') {
               $params [] = getcwd ();
            } else {
               continue 2;
            }
         }

         $commands [] = new $className (...$params);
      }

      return $commands;
   }
}
