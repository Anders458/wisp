<?php

namespace Wisp\Routing;

use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route as SymfonyRoute;
use Wisp\Container;

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
      $fullPath = $route->buildFullPath ();

      $action = $route->getAction ();

      if (is_array ($action)) {
         Container::instance ()
            ->register ($action [0])
            ->setAutowired (true)
            ->setPublic (true)
            ->setShared (false);
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

   public function __call (string $method, array $args) : RouteGroup | Route
   {
      return $this->root->$method (... $args);
   }
}
