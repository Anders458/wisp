<?php

namespace Wisp\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Generates or propagates request IDs for request tracing.
 *
 * Accepts X-Request-ID header from upstream, or generates a new one.
 * Adds request_id to request attributes and X-Request-ID to response.
 */
class RequestIdSubscriber implements EventSubscriberInterface
{
   public static function getSubscribedEvents (): array
   {
      return [
         KernelEvents::REQUEST => [ 'onRequest', 255 ],
         KernelEvents::RESPONSE => [ 'onResponse', -255 ]
      ];
   }

   public function onRequest (RequestEvent $event): void
   {
      if ($event->getRequestType () !== HttpKernelInterface::MAIN_REQUEST) {
         return;
      }

      $request = $event->getRequest ();

      $requestId = $request->headers->get ('X-Request-ID')
         ?? bin2hex (random_bytes (8));

      $request->attributes->set ('request_id', $requestId);
   }

   public function onResponse (ResponseEvent $event): void
   {
      if ($event->getRequestType () !== HttpKernelInterface::MAIN_REQUEST) {
         return;
      }

      $requestId = $event->getRequest ()->attributes->get ('request_id');

      if ($requestId) {
         $event->getResponse ()->headers->set ('X-Request-ID', $requestId);
      }
   }
}
