<?php

namespace Wisp;

use Closure;
use Wisp\Pipeline\Hook;

class Route
{
   use Guardable;
   use Pipeline;

   private string $name;

   public function __construct (
      private RouteGroup $parent,
      private array $methods,
      private string $path,
      private array | Closure $action
   ) {
      $this->name = $this->path;
   }

   public function getAction () : mixed
   {
      return $this->action;
   }

   public function getMethods () : array
   {
      return $this->methods;
   }

   public function getName () : string
   {
      return $this->name;
   }

   public function getPath () : string
   {
      return $this->path;
   }

   public function getPriority () : int
   {
      return $this->priority;
   }

   public function getParent () : RouteGroup
   {
      return $this->parent;
   }

   public function getRouter () : Router
   {
      return $this->parent->getRouter ();
   }

   public function getPipeline (Hook $hook) : array
   {
      $pipes = [];
      $pipes [] = $this->{$hook->value};

      // Collect from parent hierarchy
      $current = $this->parent;

      while ($current) {
         if (isset ($current->{$hook->value})) {
            $pipes [] = $current->{$hook->value};
         }

         $current = $current instanceof RouteGroup
            ? $current->getParent ()
            : null;
      }

      if ($hook === Hook::Before) {
         $pipes = array_reverse ($pipes);
      }

      $handlers = array_merge (... $pipes);

      // Stable sort by priority (higher priority first)
      usort ($handlers, fn ($a, $b) => $b->priority->value <=> $a->priority->value);

      return $handlers;
   }

   public function name (string $name) : self
   {
      $this->name = $name;
      return $this;
   }

   public function __call (string $method, array $args) : Route | RouteGroup
   {
      return $this->parent->$method (... $args);
   }
}
