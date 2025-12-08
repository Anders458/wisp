<?php

namespace Wisp\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Wisp\Attribute\Cached;

/**
 * Applies cache headers based on #[Cached] attribute.
 *
 * Flow:
 *   1. On controller event, read #[Cached] from controller/method
 *   2. On response event, apply Cache-Control headers
 *
 * Only caches:
 *   - GET/HEAD requests
 *   - 2xx responses
 */
class CacheSubscriber implements EventSubscriberInterface
{
   private ?Cached $cacheConfig = null;

   public static function getSubscribedEvents (): array
   {
      return [
         KernelEvents::CONTROLLER => [ 'onController', 0 ],
         KernelEvents::RESPONSE => [ 'onResponse', -15 ] // After EnvelopeSubscriber (-10)
      ];
   }

   public function onController (ControllerEvent $event): void
   {
      if (!$event->isMainRequest ()) {
         return;
      }

      $controller = $event->getController ();

      if (is_array ($controller)) {
         [ $instance, $method ] = $controller;
         $this->cacheConfig = $this->resolveCachedAttribute ($instance, $method);
      }
   }

   public function onResponse (ResponseEvent $event): void
   {
      if (!$event->isMainRequest ()) {
         return;
      }

      if ($this->cacheConfig === null) {
         return;
      }

      $request = $event->getRequest ();
      $response = $event->getResponse ();

      // Only cache GET/HEAD requests
      if (!in_array ($request->getMethod (), [ 'GET', 'HEAD' ])) {
         $this->cacheConfig = null;
         return;
      }

      // Only cache successful responses
      $statusCode = $response->getStatusCode ();
      if ($statusCode < 200 || $statusCode >= 300) {
         $this->cacheConfig = null;
         return;
      }

      $ttl = $this->cacheConfig->ttl;
      $isPublic = $this->cacheConfig->public;
      $vary = $this->cacheConfig->vary;

      if ($ttl === 0) {
         // Explicit no-cache
         $response->headers->set ('Cache-Control', 'no-store, no-cache, must-revalidate');
         $this->cacheConfig = null;
         return;
      }

      // Build Cache-Control header
      $directives = [];

      if ($isPublic) {
         $directives [] = 'public';
      } else {
         $directives [] = 'private';
         $directives [] = 'must-revalidate';
      }

      $directives [] = sprintf ('max-age=%d', $ttl);
      $response->headers->set ('Cache-Control', implode (', ', $directives));

      // Set Expires header
      $response->headers->set ('Expires', gmdate ('D, d M Y H:i:s', time () + $ttl) . ' GMT');

      // Set ETag based on content
      $content = $response->getContent ();
      if ($content !== false && $content !== '') {
         $response->headers->set ('ETag', '"' . md5 ($content) . '"');
      }

      // Apply Vary headers
      if (!empty ($vary)) {
         $existing = $response->headers->get ('Vary');
         $headers = $vary;

         if ($existing !== null) {
            $headers = array_merge (explode (', ', $existing), $vary);
         }

         $response->headers->set ('Vary', implode (', ', array_unique ($headers)));
      }

      $this->cacheConfig = null;
   }

   private function resolveCachedAttribute (object $controller, string $method): ?Cached
   {
      $methodRef = new \ReflectionMethod ($controller, $method);
      $methodAttrs = $methodRef->getAttributes (Cached::class);

      if (!empty ($methodAttrs)) {
         return $methodAttrs [0]->newInstance ();
      }

      $classRef = new \ReflectionClass ($controller);
      $classAttrs = $classRef->getAttributes (Cached::class);

      if (!empty ($classAttrs)) {
         return $classAttrs [0]->newInstance ();
      }

      return null;
   }
}
