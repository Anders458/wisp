<?php

namespace Wisp\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Wisp\Http\Request;
use Wisp\Http\Response;
use Wisp\Service\Flash;

class EnvelopeSubscriber implements EventSubscriberInterface
{
   private float $startTime;

   public function __construct (
      private Flash $flash,
      private ValidatorInterface $validator,
      private string $version = '1.0.0',
      private string $env = 'prod',
      private bool $debug = false,
      private bool $enabled = true,
      private bool $includeDebugInfo = true
   )
   {
      $this->startTime = microtime (true);
   }

   public static function getSubscribedEvents (): array
   {
      return [
         KernelEvents::REQUEST => [ 'onRequest', 1000 ],
         KernelEvents::RESPONSE => [ 'onResponse', -10 ]
      ];
   }

   public function onRequest (RequestEvent $event): void
   {
      if (!$event->isMainRequest ()) {
         return;
      }

      // Set shared services for Request/Response shortcuts
      Response::setSharedFlash ($this->flash);
      Request::setSharedValidator ($this->validator);
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

      if ($response->headers->has ('Content-Disposition')) {
         return;
      }

      $request = $event->getRequest ();

      $envelope = [
         'version' => $this->version,
         'status' => $response->getStatusCode (),
         'env' => $this->env,
         'debug' => $this->debug,
         'elapsed' => round (microtime (true) - $this->startTime, 4),
         'timestamp' => gmdate ('Y-m-d\TH:i:s\Z')
      ];

      $requestId = $request->attributes->get ('request_id');
      if ($requestId) {
         $envelope ['request_id'] = $requestId;
      }

      if ($this->includeDebugInfo && $this->debug) {
         $envelope ['memory'] = round (memory_get_peak_usage (true) / 1024 / 1024) . ' MB';
      }

      $envelope ['meta'] = [
         'method' => $request->getMethod (),
         'path' => $request->getPathInfo ()
      ];

      $query = $request->query->all ();

      if (!empty ($query)) {
         $envelope ['meta'] ['query'] = $query;
      }

      $params = $request->attributes->get ('_route_params', []);

      if (!empty ($params)) {
         $envelope ['meta'] ['params'] = $params;
      }

      $flashData = $this->flash->consume ();

      if (!empty ($flashData)) {
         $envelope ['flash'] = $flashData;
      }

      $pagination = Response::consumePagination ();

      if ($pagination !== null) {
         $envelope ['pagination'] = $pagination->toArray ();
      }

      $content = $response->getContent ();

      if (!empty ($content) && $content !== 'null' && $content !== '[]') {
         $decoded = json_decode ($content, true);

         if (json_last_error () === JSON_ERROR_NONE && $decoded !== null && $decoded !== []) {
            // Check if this is an error response with trace - extract trace separately
            if (isset ($decoded ['trace']) && isset ($decoded ['type'])) {
               $trace = $decoded ['trace'];
               unset ($decoded ['trace']);
               $envelope ['body'] = $decoded;

               if ($this->debug) {
                  $envelope ['trace'] = $trace;
               }
            } else {
               $envelope ['body'] = $decoded;
            }
         }
      }

      $newResponse = new JsonResponse ($envelope, $response->getStatusCode ());
      $newResponse->headers->add ($response->headers->all ());

      $event->setResponse ($newResponse);
   }
}
