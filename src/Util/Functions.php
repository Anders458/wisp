<?php

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Wisp\Container;
use Wisp\Exception\ServiceResolutionException;

function container (?string $service = null) : mixed
{
   $container = Container::instance ();

   if ($service) {
      return $container->get ($service);
   }

   return $container;
}

function logger () : LoggerInterface
{
   return container ()->get (LoggerInterface::class);
}

function cache () : CacheItemPoolInterface
{
   return container ()->get (CacheItemPoolInterface::class);
}

function session () : SessionInterface
{
   return container ()->get (SessionInterface::class);
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
      throw ServiceResolutionException::classNotFound ($callable [0]);
   }

   return function () use ($callable) {
      $instance = container ()->get ($callable [0]);

      if (!method_exists ($instance, $callable [1])) {
         throw ServiceResolutionException::methodNotFound ($callable [0], $callable [1]);
      }

      $method = $callable [1];

      return Closure::fromCallable ([ $instance, $method ]);
   };
}

function sha256 (string $data) : string
{
   return hash ('sha256', $data);
}

function __ (string $id, array $parameters = [], ?string $domain = null, ?string $locale = null) : string
{
   return container (\Symfony\Contracts\Translation\TranslatorInterface::class)->trans ($id, $parameters, $domain, $locale);
}