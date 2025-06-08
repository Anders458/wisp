<?php

namespace Wisp;

use Exception;
use Wisp\Http\Response;
use Wisp\Middleware\CORS;
use Wisp\Pipeline\Stage;

trait Pipeline
{
   const BEFORE_HOOK_PRIORITY = 50;
   const LISTENER_PRIORITY    = 40;
   const MIDDLEWARE_PRIORITY  = 30;
   const GUARD_PRIORITY       = 20;
   const AFTER_HOOK_PRIORITY  = 10;

   public array $stages = [
      Stage::before->value => [],
      Stage::after->value => []
   ];

   public function after (callable $action, int $priority = self::AFTER_HOOK_PRIORITY) : self
   {
      return $this->pipe (Stage::after, $action, $priority);
   }

   public function before (callable $action, int $priority = self::BEFORE_HOOK_PRIORITY) : self
   {
      return $this->pipe (Stage::before, $action, $priority);
   }

   public function getActions (Stage $stage) : array
   {
      $actions = [];
      $current = $this;

      $depth = 0;

      while ($current) {
         foreach ($current->stages [$stage->value] as $order => $step) {
            $actions [] = $step + [
               'depth' => $depth,
               'order' => $order
            ];
         }

         $current = $current->parent ?? null;
         $depth++;
      }

      usort (
         $actions,
         function ($actionX, $actionY) use ($stage) {
            $x = $actionY ['priority'] <=> $actionX ['priority'];

            if ($x !== 0) {
               return $x;
            }

            if ($stage === Stage::before) {
               $x = $actionY ['depth'] <=> $actionX ['depth'];
            } else if ($stage === Stage::after) {
               $x = $actionX ['depth'] <=> $actionY ['depth'];
            }

            if ($x !== 0) {
               return $x;
            }

            if ($stage === Stage::before) {
               return $actionX ['order'] <=> $actionY ['order'];            
            } else if ($stage === Stage::after) {
               return $actionY ['order'] <=> $actionX ['order'];
            }
         }
      );

      return array_column ($actions, 'action');
   }

   public function guard (?string $guard = null) : Guard
   {
      if (!$guard) {
         $guard = Wisp::config () ['defaults.guard'];
      }
      
      if (!isset (Wisp::config () ["guards.$guard"])) {
         throw new Exception ('No definition found for guard: ' . $guard . '. Guards must be registered through Wisp::setup');
      }

      $guard = new Guard (
         $this, 
         $guard
      );

      $this->before ($guard, self::GUARD_PRIORITY);

      return $guard;
   }

   public function middleware ($middleware, int $priority = self::MIDDLEWARE_PRIORITY) : self
   {
      if (method_exists ($middleware, Stage::before->value)) {
         $this->before (fn (Container $container) => 
            $container->run (Invokable::from ([ $middleware, 'before' ])), $priority);
      }

      if (method_exists ($middleware, Stage::after->value)) {
         $this->after (fn (Container $container) =>
            $container->run (Invokable::from ([ $middleware, 'after' ])), $priority);
      }

      if ($middleware instanceof CORS) {
         $this->options ('/.*', fn () => 0);
      }

      return $this;
   }

   public function on (int $code, mixed $action, int $priority = self::LISTENER_PRIORITY) : self
   {
      $this->after (function (Response $response, Container $container) use ($code, $action) {
         if ($response->code === $code) {
            $container->run (Invokable::from ($action));
         }
      }, $priority);

      return $this;
   }

   public function pipe (Stage $stage, callable $action, int $priority) : self
   {
      $this->stages [$stage->value] [] = [
         'action' => Invokable::from ($action),
         'priority' => $priority
      ];

      return $this;
   }
}