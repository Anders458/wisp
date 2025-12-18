<?php

namespace Wisp\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Wisp\Exception\ValidationException;
use Wisp\Service\Flash;

class ExceptionSubscriber implements EventSubscriberInterface
{
   public function __construct (
      private Flash $flash
   )
   {
   }

   public static function getSubscribedEvents (): array
   {
      return [
         KernelEvents::EXCEPTION => [ 'onException', 10 ]
      ];
   }

   public function onException (ExceptionEvent $event): void
   {
      $exception = $event->getThrowable ();

      // Handle Wisp ValidationException (from Request::validate())
      if ($exception instanceof ValidationException) {
         $this->flash->violations ($exception->getViolations ());
         $event->setResponse (new JsonResponse (null, 422));
         return;
      }

      // Handle Symfony's HttpException (which wraps ValidationFailedException from MapRequestPayload)
      if ($exception instanceof HttpExceptionInterface) {
         $previous = $exception->getPrevious ();

         if ($previous instanceof ValidationFailedException) {
            $this->flash->violations ($previous->getViolations ());
            $event->setResponse (new JsonResponse (null, $exception->getStatusCode ()));
            return;
         }

         // Handle BadRequestHttpException (invalid JSON, etc.)
         if ($exception instanceof BadRequestHttpException) {
            $this->flash->error ($exception->getMessage (), 'request:bad_request');
            $event->setResponse (new JsonResponse (null, 400));
            return;
         }

         // Handle all other HttpExceptions (401, 403, 404, etc.)
         // Convert to flash message to avoid Symfony's RFC 7807 problem details in body
         $statusCode = $exception->getStatusCode ();
         $errorCode = $this->getErrorCodeForStatus ($statusCode);
         $this->flash->error ($exception->getMessage () ?: $this->getDefaultMessageForStatus ($statusCode), $errorCode);
         $event->setResponse (new JsonResponse (null, $statusCode));
         return;
      }

      // Handle AccessDeniedException
      if ($exception instanceof AccessDeniedException) {
         $this->flash->error ($exception->getMessage () ?: 'Access denied', 'auth:forbidden');
         $event->setResponse (new JsonResponse (null, 403));
         return;
      }

      // Handle AuthenticationException
      if ($exception instanceof AuthenticationException) {
         $this->flash->error ($exception->getMessage () ?: 'Authentication required', 'auth:unauthenticated');
         $event->setResponse (new JsonResponse (null, 401));
      }
   }

   private function getErrorCodeForStatus (int $status): string
   {
      return match ($status) {
         400 => 'request:bad_request',
         401 => 'auth:unauthenticated',
         403 => 'auth:forbidden',
         404 => 'resource:not_found',
         405 => 'request:method_not_allowed',
         409 => 'resource:conflict',
         422 => 'validation:failed',
         429 => 'request:rate_limited',
         default => 'error:http_' . $status
      };
   }

   private function getDefaultMessageForStatus (int $status): string
   {
      return match ($status) {
         400 => 'Bad request',
         401 => 'Authentication required',
         403 => 'Access denied',
         404 => 'Resource not found',
         405 => 'Method not allowed',
         409 => 'Resource conflict',
         422 => 'Validation failed',
         429 => 'Too many requests',
         default => 'An error occurred'
      };
   }
}
