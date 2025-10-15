<?php

namespace Wisp;

use Closure;

class RouteGroup
{
   use Pipeline;
   use Routable;

   private ?RouteGroup $parent;
   private ?string     $path;
   private array       $groups;
   private array       $routes;

   public function __construct (
      private Router $router,
      ?RouteGroup    $parent = null,
      ?string        $path = null
   ) {
      $this->parent = $parent;
      $this->path   = $path;
      $this->groups = [];
      $this->routes = [];
   }

   public function add (array $methods, string $path, array | Closure $action) : Route
   {
      $route = new Route (
         $this,
         $methods,
         $path,
         $action
      );

      $this->routes [] = $route;
      $this->router->register ($route);

      return $route;
   }

   public function getParent () : ?RouteGroup
   {
      return $this->parent;
   }

   public function getPath () : ?string
   {
      return $this->path;
   }

   public function getRouter () : Router
   {
      return $this->router;
   }

   public function group (string $path, callable $callback) : self
   {
      $group = new self (
         $this->router,
         $this,
         $path
      );

      // $this->groups [] = $group;

      $callback ($group);

      return $this;
   }

   // public function __call (string $method, array $args) : Route | RouteGroup | Router
   // {
   //    if ($this->parent) {
   //       return $this->parent->$method (... $args);
   //    }

   //    return $this->router->$method (... $args);
   // }
}
