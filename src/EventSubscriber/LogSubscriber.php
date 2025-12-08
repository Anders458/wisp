<?php

namespace Wisp\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Wisp\Attribute\Log;

/**
 * Logs API requests based on #[Log] attribute configuration.
 */
class LogSubscriber implements EventSubscriberInterface
{
   public function __construct (
      private LoggerInterface $logger
   ) {}

   public static function getSubscribedEvents (): array
   {
      return [
         KernelEvents::CONTROLLER => [ 'onController', 0 ],
         KernelEvents::RESPONSE => [ 'onResponse', -256 ]
      ];
   }

   public function onController (ControllerEvent $event): void
   {
      if ($event->getRequestType () !== HttpKernelInterface::MAIN_REQUEST) {
         return;
      }

      $controller = $event->getController ();

      if (is_array ($controller)) {
         $controller = $controller [0];
      }

      $request = $event->getRequest ();
      $method = $request->attributes->get ('_controller');

      $logAttr = $this->getLogAttribute ($controller, $method);

      if ($logAttr) {
         $request->attributes->set ('_log_config', $logAttr);
         $request->attributes->set ('_log_start', hrtime (true));
      }
   }

   public function onResponse (ResponseEvent $event): void
   {
      if ($event->getRequestType () !== HttpKernelInterface::MAIN_REQUEST) {
         return;
      }

      $request = $event->getRequest ();
      $config = $request->attributes->get ('_log_config');

      if (!$config instanceof Log) {
         return;
      }

      $startTime = $request->attributes->get ('_log_start', hrtime (true));
      $duration = (hrtime (true) - $startTime) / 1e6;

      $context = [
         'request_id' => $request->attributes->get ('request_id'),
         'method' => $request->getMethod (),
         'path' => $request->getPathInfo (),
         'status' => $event->getResponse ()->getStatusCode (),
         'duration_ms' => round ($duration, 2)
      ];

      if (in_array ('request', $config->include)) {
         $body = json_decode ($request->getContent (), true) ?? [];
         $context ['request_body'] = $this->redact ($body, $config->redact);
      }

      if (in_array ('response', $config->include)) {
         $body = json_decode ($event->getResponse ()->getContent (), true) ?? [];
         $context ['response_body'] = $this->redact ($body, $config->redact);
      }

      if (in_array ('headers', $config->include)) {
         $context ['request_headers'] = $this->redact (
            $request->headers->all (),
            array_merge ($config->redact, [ 'authorization', 'cookie' ])
         );
      }

      $this->logger->log ($config->level, 'API request', $context);
   }

   private function getLogAttribute (object $controller, ?string $method): ?Log
   {
      $reflection = new \ReflectionClass ($controller);

      // Check method first
      if ($method && str_contains ($method, '::')) {
         $methodName = explode ('::', $method) [1];
         if ($reflection->hasMethod ($methodName)) {
            $attrs = $reflection->getMethod ($methodName)->getAttributes (Log::class);
            if (!empty ($attrs)) {
               return $attrs [0]->newInstance ();
            }
         }
      }

      // Check class
      $attrs = $reflection->getAttributes (Log::class);
      if (!empty ($attrs)) {
         return $attrs [0]->newInstance ();
      }

      return null;
   }

   private function redact (array $data, array $keys): array
   {
      foreach ($keys as $key) {
         $key = strtolower ($key);
         foreach ($data as $k => $v) {
            if (strtolower ($k) === $key) {
               $data [$k] = '****';
            }
         }
      }

      return $data;
   }
}
