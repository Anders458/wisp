<?php

namespace Wisp\Routing;

use ReflectionClass;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Routing\RouteCollection;
use Wisp\Attribute\Version;

/**
 * Decorates the attribute route loader to apply #[Version] prefixes.
 */
class VersionedAttributeLoader implements LoaderInterface
{
   private array $versionCache = [];

   public function __construct (
      private LoaderInterface $inner
   )
   {
   }

   public function load (mixed $resource, ?string $type = null): RouteCollection
   {
      $routes = $this->inner->load ($resource, $type);

      // $resource is the controller class name
      if (is_string ($resource) && class_exists ($resource)) {
         $version = $this->findVersionAttribute ($resource);

         if ($version !== null) {
            $prefix = '/' . ltrim ($version->prefix, '/');

            foreach ($routes->all () as $route) {
               $currentPath = $route->getPath ();

               if (!str_starts_with ($currentPath, $prefix)) {
                  $route->setPath ($prefix . $currentPath);
               }
            }
         }
      }

      return $routes;
   }

   public function supports (mixed $resource, ?string $type = null): bool
   {
      return $this->inner->supports ($resource, $type);
   }

   public function getResolver (): \Symfony\Component\Config\Loader\LoaderResolverInterface
   {
      return $this->inner->getResolver ();
   }

   public function setResolver (\Symfony\Component\Config\Loader\LoaderResolverInterface $resolver): void
   {
      $this->inner->setResolver ($resolver);
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
