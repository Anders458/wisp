<?php

namespace Wisp\EventSubscriber;

use ReflectionClass;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Wisp\Attribute\Version;

class VersionSubscriber implements EventSubscriberInterface
{
   private bool $initialized = false;
   private array $versionCache = [];

   public function __construct (
      private RouterInterface $router
   )
   {
   }

   public static function getSubscribedEvents (): array
   {
      return [
         KernelEvents::REQUEST => [ 'onRequest', 256 ]
      ];
   }

   public function onRequest (RequestEvent $event): void
   {
      if ($this->initialized) {
         return;
      }

      $this->initialized = true;
      $this->applyVersionPrefixes ();
   }

   private function applyVersionPrefixes (): void
   {
      $routes = $this->router->getRouteCollection ();

      foreach ($routes->all () as $name => $route) {
         $controller = $route->getDefault ('_controller');

         if ($controller === null) {
            continue;
         }

         $class = $this->extractControllerClass ($controller);

         if ($class === null) {
            continue;
         }

         $version = $this->findVersionAttribute ($class);

         if ($version !== null) {
            $prefix = '/' . ltrim ($version->prefix, '/');
            $currentPath = $route->getPath ();

            // Avoid double-prefixing
            if (!str_starts_with ($currentPath, $prefix)) {
               $route->setPath ($prefix . $currentPath);
            }
         }
      }
   }

   private function extractControllerClass (mixed $controller): ?string
   {
      if (is_string ($controller)) {
         if (str_contains ($controller, '::')) {
            return explode ('::', $controller) [0];
         }

         if (class_exists ($controller)) {
            return $controller;
         }
      }

      if (is_array ($controller) && isset ($controller [0])) {
         return is_object ($controller [0]) ? $controller [0]::class : $controller [0];
      }

      return null;
   }

   private function findVersionAttribute (string $class): ?Version
   {
      if (isset ($this->versionCache [$class])) {
         return $this->versionCache [$class];
      }

      if (!class_exists ($class)) {
         return $this->versionCache [$class] = null;
      }

      $reflection = new ReflectionClass ($class);

      // Walk up the class hierarchy
      while ($reflection) {
         $attributes = $reflection->getAttributes (Version::class);

         if (!empty ($attributes)) {
            return $this->versionCache [$class] = $attributes [0]->newInstance ();
         }

         $reflection = $reflection->getParentClass ();
      }

      return $this->versionCache [$class] = null;
   }
}
