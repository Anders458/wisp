<?php

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Wisp\Wisp;

function container () : ContainerBuilder
{
   return Wisp::container ();
}

/**
 * Lazily resolves a service method from the container.
 *
 * Given a [FQCN, method] tuple, returns a closure that, when invoked,
 * retrieves the service instance from the container and returns a callable
 * bound to the specified method. This allows deferred resolution of services
 * (useful in pipelines or middleware) while keeping all handlers as closures.
 *
 * Example:
 *   $deferred = defer ([ AuthMiddleware::class, 'before' ]);
 *   $callable = $deferred ();   // resolves the service + method
 *   $callable ($request);       // executes AuthMiddleware::before($request)
 */
function defer (array $callable) : Closure
{
   if (!class_exists ($callable [0])) {
      throw new Exception ('Deferred callable resolution must be a [ FQCN, method ] tuple');
   }

   return function () use ($callable) {
      $instance = container ()->get ($callable [0]);

      if (!method_exists ($instance, $callable [1])) {
         throw new Exception ('Method ' . $callable [1] . ' does not exist on ' . $callable [0]);
      }

      $method = $callable [1];
      
      return Closure::fromCallable ([ $instance, $method ]);
   };
}