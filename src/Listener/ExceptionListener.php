<?php

namespace Wisp\Listener;

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
      private RuntimeInterface $runtime
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

      if ($exception instanceof ValidationException) {
         $response = $exception->getResponse ();
         $event->setResponse ($response);
         return;
      }

      if ($exception instanceof AccessDeniedException) {
         $response->headers->set ('Content-Type', 'application/json');
         $response->status (403);
         $event->setResponse ($response);
         return;
      }

      if ($exception instanceof AuthenticationException) {
         $response->headers->set ('Content-Type', 'application/json');
         $response->status (401);
         $event->setResponse ($response);
         return;
      }

      if ($exception instanceof HttpExceptionInterface) {
         $statusCode = $exception->getStatusCode ();
         $headers = $exception->getHeaders ();

         $response->headers->set ('Content-Type', 'application/json');
         $response->status ($statusCode);

         foreach ($headers as $key => $value) {
            $response->headers->set ($key, $value);
         }

         $event->setResponse ($response);
         return;
      }

      $response->headers->set ('Content-Type', 'application/json');
      $response->status (500);
      $event->setResponse ($response);
   }
}
