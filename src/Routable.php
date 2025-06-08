<?php

namespace Wisp;

trait Routable
{
   public function connect (string $pattern, mixed $action) : Route
   {
      return $this->add ([ 'CONNECT' ], $pattern, $action);
   }

   public function delete (string $pattern, mixed $action) : Route
   {
      return $this->add ([ 'DELETE' ], $pattern, $action);
   }

   public function get (string $pattern, mixed $action) : Route
   {
      return $this->add ([ 'GET' ], $pattern, $action);
   }

   public function head (string $pattern, mixed $action) : Route
   {
      return $this->add ([ 'HEAD' ], $pattern, $action);
   }

   public function options (string $pattern, mixed $action) : Route
   {
      return $this->add ([ 'OPTIONS' ], $pattern, $action);
   }

   public function patch (string $pattern, mixed $action) : Route
   {
      return $this->add ([ 'PATCH' ], $pattern, $action);
   }

   public function post (string $pattern, mixed $action) : Route
   {
      return $this->add ([ 'POST' ], $pattern, $action);
   }

   public function put (string $pattern, mixed $action) : Route
   {
      return $this->add ([ 'PUT' ], $pattern, $action);
   }

   public function trace (string $pattern, mixed $action) : Route
   {
      return $this->add ([ 'TRACE' ], $pattern, $action);
   }

   public function any (string $pattern, mixed $action) : Route
   {
      return $this->add ([
         'CONNECT',
         'DELETE',
         'GET',
         'HEAD',
         'OPTIONS',
         'PATCH',
         'POST',
         'PUT',
         'TRACE'
      ], $pattern, $action);
   }

   public function match (string $pattern, mixed $action, array $methods) : Route
   {
      return $this->add ($methods, $pattern, $action);
   }

   abstract public function add (array $methods, string $pattern, mixed $action) : Route;
}