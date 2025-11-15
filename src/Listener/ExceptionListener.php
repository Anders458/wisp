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

      // Handle ValidationException
      if ($exception instanceof ValidationException) {
         $response = $exception->getResponse ();
         $event->setResponse ($response);
         return;
      }

      // Handle Access Denied (403)
      if ($exception instanceof AccessDeniedException) {
         $response
            ->status (403)
            ->json ([
               'error' => 'Access denied',
               'message' => $this->runtime->isDebug () ? $exception->getMessage () : 'You do not have permission to access this resource'
            ]);

         $event->setResponse ($response);
         return;
      }

      // Handle Authentication (401)
      if ($exception instanceof AuthenticationException) {
         $response
            ->status (401)
            ->json ([
               'error' => 'Unauthorized',
               'message' => $this->runtime->isDebug () ? $exception->getMessage () : 'Authentication required'
            ]);

         $event->setResponse ($response);
         return;
      }

      // Handle HTTP exceptions
      if ($exception instanceof HttpExceptionInterface) {
         $statusCode = $exception->getStatusCode ();
         $headers = $exception->getHeaders ();

         $response
            ->status ($statusCode)
            ->json ([
               'error' => Response::$statusTexts [$statusCode] ?? 'Error',
               'message' => $this->runtime->isDebug () ? $exception->getMessage () : Response::$statusTexts [$statusCode] ?? 'An error occurred'
            ]);

         foreach ($headers as $key => $value) {
            $response->headers->set ($key, $value);
         }

         $event->setResponse ($response);
         return;
      }

      // Handle all other exceptions as 500
      $response
         ->status (500)
         ->json ([
            'error' => 'Internal Server Error',
            'message' => $this->runtime->isDebug () ? $exception->getMessage () : 'An unexpected error occurred',
            'trace' => $this->runtime->isDebug () ? $exception->getTraceAsString () : null
         ]);

      $event->setResponse ($response);
   }
}
