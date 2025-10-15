<?php

namespace Wisp;

use Closure;
use Wisp\Http\Response;
use Wisp\Pipeline\Handler;
use Wisp\Pipeline\Hook;
use Wisp\Pipeline\Priority;

trait Pipeline
{
   public array $before = [];
   public array $after  = [];

   public function after (Closure $action) : self
   {
      return $this->pipe (Hook::After, fn () => $action, Priority::Hook);
   }

   public function before (Closure $action) : self
   {
      return $this->pipe (Hook::Before, fn () => $action, Priority::Hook);
   }

   public function middleware (string $class, array $settings = []) : self
   {
      $container = Wisp::container ();

      $container
         ->register ($class)
         ->setAutowired (true)
         ->setPublic (true)
         ->setArgument ('$settings', $settings);

      if (method_exists ($class, Hook::Before->value)) {
         $this->pipe (Hook::Before, defer ([ $class, Hook::Before->value ]), Priority::Middleware);
      }

      if (method_exists ($class, Hook::After->value)) {
         $this->pipe (Hook::After, defer ([ $class, Hook::After->value ]), Priority::Middleware);
      }

      return $this;
   }

   public function on (int $code, array | Closure $action) : self
   {
      if (is_array ($action)) {
         container ()
            ->register ($action [0])
            ->setAutowired (true)
            ->setPublic (true);
      }
      
      $conditioned = function (Response $response) use ($code, $action) {
         if ($response->getStatusCode () === $code) {
            return defer ($action);
         }
      };

      return $this->pipe (Hook::After, $conditioned, Priority::Listener);
   }

   private function pipe (Hook $when, Closure $action, Priority $priority) : self
   {
      $this->{$when->value} [] = new Handler (
         $action,
         $priority
      );

      return $this;
   }
}
