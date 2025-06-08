<?php

namespace Wisp;

use Exception;

class Route extends Constraint
{
   use Pipeline;

   private string $pattern;
   private mixed $action;
   private string $name;
   private int $priority;

   public function __construct (RouteGroup $parent, array $methods, string $path, mixed $action)
   {
      parent::__construct ($parent);

      $this->path ($path);
      $this->action = $action;
      $this->name = $this->getFullPaths () [0];
      $this->priority = 0;

      foreach ($methods as $method) {
         $this->method ($method);
      }
   }

   public function getAction () : mixed
   {
      return $this->action;
   }

   public function getName () : string
   {
      return $this->name;
   }

   public function getPriority () : int
   {
      return $this->priority;
   }

   public function getRouter () : Router
   {
      return $this->parent->getRouter ();
   }

   public function name (string $name) : self
   {
      $this->name = $name;
      return $this;
   }

   public function priority (int $priority) : self
   {
      $this->priority = $priority;
      return $this;
   }

   public function __call (string $method, array $args)
   {
      return $this->parent->$method (... $args);
   }
}