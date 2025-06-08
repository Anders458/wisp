<?php

namespace Wisp;

class RouteGroup extends Constraint
{
   use Routable;
   use Pipeline;

   private Router $router;
   private array $routes;
   private array $groups;

   public function __construct (Router $router, ?self $parent, string $path)
   {
      parent::__construct ($parent);
      
      $this->router = $router;
      
      $this->path ($path);

      $this->routes = [];
      $this->groups = [];
   }

   public function add (array $methods, string $path, mixed $action) : Route
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

   public function getGroups () : array
   {
      return $this->groups;
   }

   public function getRouter () : Router
   {
      return $this->router;
   }

   public function getRoutes () : array
   {
      return $this->routes;
   }

   public function group (string $path, callable $callback) : self
   {
      $group = new self (
         $this->router, 
         $this, 
         $path
      );

      $this->groups [] = $group;

      $callback ($group);
      return $this;
   }

   public function redirect (string $from, string $to, int $httpStatusCode) : self
   {
      return $this;
   }
}