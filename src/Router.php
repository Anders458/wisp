<?php

namespace Wisp;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Routing\Matcher\Dumper\CompiledUrlMatcherDumper;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route as SymfonyRoute;
use Wisp\Environment\RuntimeInterface;

class Router
{
   public RouteGroup      $root;
   public RouteCollection $routes;

   private array $registry = [];

   public function __construct ()
   {
      $this->root   = new RouteGroup ($this);
      $this->routes = new RouteCollection ();
   }

   public function register (Route $route) : self
   {
      $fullPath = $this->buildFullPath ($route);

      $action = $route->getAction ();

      if (is_array ($action)) {
         Container::instance ()
            ->register ($action [0])
            ->setAutowired (true)
            ->setPublic (true);
      }

      $symfonyRoute = new SymfonyRoute (
         $fullPath,
         [
            '_controller' => $action
         ],
         [],
         [],
         '',
         [],
         $route->getMethods ()
      );

      $name = $route->getName ();
      $this->routes->add ($name, $symfonyRoute);

      $this->registry [$name] = $route;

      return $this;
   }

   public function find (string $name) : ?Route
   {
      return $this->registry [$name] ?? null;
   }

   private function buildFullPath (Route $route) : string
   {
      $parts = [];
      $current = $route->getParent ();

      while ($current) {
         if ($current->getPath ()) {
            array_unshift ($parts, $current->getPath ());
         }

         $current = $current->getParent ();
      }

      $parts [] = $route->getPath ();

      return implode ('', $parts);
   }

   public function isCacheValid (RuntimeInterface $runtime) : bool
   {
      $cacheFile = $runtime->getRoot () . '/var/cache/routes/routes.cache';
      return file_exists ($cacheFile);
   }

   public function warmup (RuntimeInterface $runtime) : void
   {
      return;
      
      $cacheDir = $runtime->getRoot () . '/var/cache/routes';
      $cacheFile = $cacheDir . '/routes.cache';

      if (!is_dir ($cacheDir)) {
         mkdir ($cacheDir, 0755, true);
      }

      // Check if any route uses a closure (not serializable)
      foreach ($this->routes->all () as $route) {
         $controller = $route->getDefault ('_controller');
         if ($controller instanceof \Closure) {
            // Cannot cache routes with closures
            return;
         }
      }

      $dumper = new CompiledUrlMatcherDumper ($this->routes);
      $compiledRoutes = $dumper->getCompiledRoutes ();

      file_put_contents ($cacheFile, serialize ($compiledRoutes), LOCK_EX);
   }

   public function __call (string $method, array $args) : RouteGroup | Route
   {
      return $this->root->$method (... $args);
   }
}
