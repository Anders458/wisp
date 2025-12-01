<?php

namespace Wisp\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Wisp\Exception\JsonParseException;
use Wisp\Exception\ValidationException;

class ValidationExceptionSubscriber implements EventSubscriberInterface
{
   public static function getSubscribedEvents (): array
   {
      return [
         KernelEvents::EXCEPTION => [ 'onException', 10 ]
      ];
   }

   public function onException (ExceptionEvent $event): void
   {
      $exception = $event->getThrowable ();

      if ($exception instanceof ValidationException) {
         $response = new JsonResponse ([
            'error' => 'Validation failed',
            'errors' => $exception->toArray ()
         ], 422);

         $event->setResponse ($response);
         return;
      }

      if ($exception instanceof JsonParseException) {
         $response = new JsonResponse ([
            'error' => 'Invalid JSON',
            'message' => $exception->getMessage ()
         ], 400);

         $event->setResponse ($response);
      }
   }
}
