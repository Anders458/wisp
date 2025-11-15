<?php

namespace Wisp;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route as SymfonyRoute;

class Router
{
   public RouteGroup      $root;
   public RouteCollection $routes;

   private array $registry = [];
   private ?string $cacheDir = null;
   private bool $debug = false;

   public function __construct ()
   {
      $this->root   = new RouteGroup ($this);
      $this->routes = new RouteCollection ();
   }

   public function register (Route $route) : self
   {
      $fullPath = $this->buildFullPath ($route);

      $action = $route->getAction ();

      // If action is [ClassName::class, 'method'], register the controller in container
      if (is_array ($action)) {
         Container::instance ()
            ->register ($action [0])
            ->setAutowired (true)
            ->setPublic (true);
      }

      // Create Symfony route
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

      // Add to Symfony RouteCollection
      $name = $route->getName ();
      $this->routes->add ($name, $symfonyRoute);

      // Register in our internal registry
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

   public function isCacheValid () : bool
   {
      if ($this->debug || !$this->cacheDir) {
         return false;
      }

      $cacheFile = $this->getCacheFile ();
      return file_exists ($cacheFile);
   }

   public function loadFromCache () : void
   {
      if (!$this->cacheDir) {
         return;
      }

      $cacheFile = $this->getCacheFile ();

      if (!file_exists ($cacheFile)) {
         return;
      }

      $data = unserialize (file_get_contents ($cacheFile));
      $this->routes = $data ['routes'];
      $this->registry = $data ['registry'];
   }

   public function setCacheDir (string $dir) : self
   {
      $this->cacheDir = $dir;
      return $this;
   }

   public function setDebug (bool $debug) : self
   {
      $this->debug = $debug;
      return $this;
   }

   public function warmup () : void
   {
      if ($this->debug || !$this->cacheDir) {
         return;
      }

      if (!is_dir ($this->cacheDir)) {
         mkdir ($this->cacheDir, 0755, true);
      }

      $cacheFile = $this->getCacheFile ();
      $data = [
         'routes' => $this->routes,
         'registry' => $this->registry
      ];

      file_put_contents ($cacheFile, serialize ($data));
   }

   public function __call (string $method, array $args) : RouteGroup | Route
   {
      return $this->root->$method (... $args);
   }

   private function getCacheFile () : string
   {
      return $this->cacheDir . '/routes.cache';
   }
}
