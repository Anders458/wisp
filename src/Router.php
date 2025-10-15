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

   public function __construct ()
   {
      $this->root   = new RouteGroup ($this);
      $this->routes = new RouteCollection ();
   }

   public function register (Route $route) : self
   {
      $container = Wisp::container ();

      $fullPath = $this->buildFullPath ($route);

      $action = $route->getAction ();

      // If action is [ClassName::class, 'method'], register the controller in container
      
      if (is_array ($action)) {
         $container
            ->register ($action [0])
            ->setAutowired (true)
            ->setAutoconfigured (true)
            ->setPublic (true);
      }

      // var_dump ($fullPath);
      // die ();

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

   public function __call (string $method, array $args) : RouteGroup | Route
   {
      return $this->root->$method (... $args);
   }
}
