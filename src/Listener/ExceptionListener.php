<?php

namespace Wisp\Listener;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Wisp\Environment\RuntimeInterface;
use Wisp\Http\Response;
use Wisp\Http\ValidationException;

class ExceptionListener implements EventSubscriberInterface
{
   public function __construct (
      private RuntimeInterface $runtime,
      private LoggerInterface $logger
   )
   {
   }

   public static function getSubscribedEvents () : array
   {
      return [
         KernelEvents::EXCEPTION => [ 'onException', 10 ]
      ];
   }

   public function onException (ExceptionEvent $event) : void
   {
      $exception = $event->getThrowable ();
      $response = new Response ();
      $request = $event->getRequest ();

      if ($exception instanceof ValidationException) {
         $this->logger->info ('Validation failed', [
            'uri' => $request->getRequestUri (),
            'method' => $request->getMethod ()
         ]);
         $response = $exception->getResponse ();
         $event->setResponse ($response);
         return;
      }

      if ($exception instanceof AccessDeniedException) {
         $this->logger->warning ('Access denied', [
            'uri' => $request->getRequestUri (),
            'method' => $request->getMethod (),
            'message' => $exception->getMessage ()
         ]);
         $response->headers->set ('Content-Type', 'application/json');
         $response->status (403);
         $event->setResponse ($response);
         return;
      }

      if ($exception instanceof AuthenticationException) {
         $this->logger->warning ('Authentication failed', [
            'uri' => $request->getRequestUri (),
            'method' => $request->getMethod (),
            'message' => $exception->getMessage ()
         ]);
         $response->headers->set ('Content-Type', 'application/json');
         $response->status (401);
         $event->setResponse ($response);
         return;
      }

      if ($exception instanceof HttpExceptionInterface) {
         $statusCode = $exception->getStatusCode ();
         $headers = $exception->getHeaders ();

         $this->logger->notice ('HTTP exception', [
            'status' => $statusCode,
            'uri' => $request->getRequestUri (),
            'method' => $request->getMethod (),
            'message' => $exception->getMessage ()
         ]);

         $response->headers->set ('Content-Type', 'application/json');
         $response->status ($statusCode);

         foreach ($headers as $key => $value) {
            $response->headers->set ($key, $value);
         }

         $event->setResponse ($response);
         return;
      }

      $this->logger->error ('Unhandled exception', [
         'uri' => $request->getRequestUri (),
         'method' => $request->getMethod (),
         'exception' => get_class ($exception),
         'message' => $exception->getMessage (),
         'file' => $exception->getFile (),
         'line' => $exception->getLine (),
         'trace' => $this->runtime->isDebug () ? $exception->getTraceAsString () : null
      ]);

      $response->headers->set ('Content-Type', 'application/json');
      $response->status (500);
      $event->setResponse ($response);
   }
}
