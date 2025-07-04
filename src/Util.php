<?php

namespace Wisp;

class Util
{
   public static function runWithHooks (Invokable $invokable) : void
   {
      $container = Container::get ();

      if ($invokable->hasMethod ('before')) {
         $container->run ($invokable->rebind ('before'));
      }

      $container->run ($invokable);

      if ($invokable->hasMethod ('after')) {
         $container->run ($invokable->rebind ('after'));
      }
   }
}