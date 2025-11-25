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

      $data = json_decode (file_get_contents ($cacheFile), true);

      $this->routes = new RouteCollection ();
      foreach ($data ['routes'] as $name => $routeData) {
         $this->routes->add ($name, new SymfonyRoute (
            $routeData ['path'],
            $routeData ['defaults'],
            $routeData ['requirements'],
            $routeData ['options'],
            $routeData ['host'],
            $routeData ['schemes'],
            $routeData ['methods']
         ));
      }

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

      $routesData = [];
      foreach ($this->routes->all () as $name => $route) {
         $routesData [$name] = [
            'path' => $route->getPath (),
            'defaults' => $route->getDefaults (),
            'requirements' => $route->getRequirements (),
            'options' => $route->getOptions (),
            'host' => $route->getHost (),
            'schemes' => $route->getSchemes (),
            'methods' => $route->getMethods ()
         ];
      }

      $data = [
         'routes' => $routesData,
         'registry' => $this->registry
      ];

      try {
         file_put_contents ($cacheFile, json_encode ($data, JSON_PRETTY_PRINT));
      } catch (\Exception $e) {
         if (file_exists ($cacheFile)) {
            unlink ($cacheFile);
         }
      }
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
