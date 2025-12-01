<?php

namespace Wisp\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Wisp\Runtime;
use Wisp\Service\Flash;

class EnvelopeSubscriber implements EventSubscriberInterface
{
   public function __construct (
      private Runtime $runtime,
      private Flash $flash,
      private bool $enabled = true,
      private bool $includeDebugInfo = true
   )
   {
   }

   public static function getSubscribedEvents (): array
   {
      return [
         KernelEvents::RESPONSE => [ 'onResponse', -10 ]
      ];
   }

   public function onResponse (ResponseEvent $event): void
   {
      if (!$this->enabled) {
         return;
      }

      if (!$event->isMainRequest ()) {
         return;
      }

      $response = $event->getResponse ();
      $contentType = $response->headers->get ('Content-Type', '');

      if (!str_contains ($contentType, 'application/json')) {
         return;
      }

      // Skip if response is a file download
      if ($response->headers->has ('Content-Disposition')) {
         return;
      }

      $request = $event->getRequest ();

      $envelope = [
         'version' => $this->runtime->version (),
         'status' => $response->getStatusCode (),
         'stage' => $this->runtime->stage ()->value,
         'timestamp' => gmdate ('Y-m-d\TH:i:s\Z')
      ];

      if ($this->includeDebugInfo && $this->runtime->isDebug ()) {
         $envelope ['debug'] = [
            'elapsed' => round ($this->runtime->elapsed (), 4),
            'memory' => round (memory_get_peak_usage (true) / 1024 / 1024, 2) . ' MB'
         ];
      }

      $envelope ['meta'] = [
         'method' => $request->getMethod (),
         'path' => $request->getPathInfo (),
         'query' => $request->query->all () ?: null
      ];

      // Remove null values from meta
      $envelope ['meta'] = array_filter ($envelope ['meta'], fn ($v) => $v !== null);

      $flashData = $this->flash->consume ();

      if (!empty ($flashData ['errors']) || !empty ($flashData ['warnings']) || $flashData ['code'] !== 0) {
         $envelope ['flash'] = array_filter ($flashData, fn ($v) => !empty ($v));
      }

      $content = $response->getContent ();

      if (!empty ($content)) {
         $decoded = json_decode ($content, true);

         if (json_last_error () === JSON_ERROR_NONE) {
            $envelope ['data'] = $decoded;
         }
      }

      $newResponse = new JsonResponse ($envelope, $response->getStatusCode ());
      $newResponse->headers->add ($response->headers->all ());

      $event->setResponse ($newResponse);
   }
}
