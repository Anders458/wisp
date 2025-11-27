<?php

namespace Wisp\Routing;

trait Routable
{
   public function any (string $path, mixed $action) : Route
   {
      return $this->add ([ 'GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD' ], $path, $action);
   }

   public function connect (string $path, mixed $action) : Route
   {
      return $this->add ([ 'CONNECT' ], $path, $action);
   }

   public function delete (string $path, mixed $action) : Route
   {
      return $this->add ([ 'DELETE' ], $path, $action);
   }

   public function get (string $path, mixed $action) : Route
   {
      return $this->add ([ 'GET' ], $path, $action);
   }

   public function head (string $path, mixed $action) : Route
   {
      return $this->add ([ 'HEAD' ], $path, $action);
   }

   public function match (string $path, mixed $action, array $methods) : Route
   {
      return $this->add ($methods, $path, $action);
   }

   public function options (string $path, mixed $action) : Route
   {
      return $this->add ([ 'OPTIONS' ], $path, $action);
   }

   public function patch (string $path, mixed $action) : Route
   {
      return $this->add ([ 'PATCH' ], $path, $action);
   }

   public function post (string $path, mixed $action) : Route
   {
      return $this->add ([ 'POST' ], $path, $action);
   }

   public function put (string $path, mixed $action) : Route
   {
      return $this->add ([ 'PUT' ], $path, $action);
   }

   public function trace (string $path, mixed $action) : Route
   {
      return $this->add ([ 'TRACE' ], $path, $action);
   }
}
