<?php

namespace Wisp\EventSubscriber;

use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Wisp\Attribute\Bearer;
use Wisp\Http\Request;
use Wisp\Security\BearerDecoderInterface;
use Wisp\Service\Flash;

class BearerSubscriber implements EventSubscriberInterface
{
   public function __construct (
      private ?BearerDecoderInterface $decoder,
      private Flash $flash
   )
   {
   }

   public static function getSubscribedEvents (): array
   {
      return [
         KernelEvents::CONTROLLER => [ 'onController', 15 ]
      ];
   }

   public function onController (ControllerEvent $event): void
   {
      if (!$event->isMainRequest ()) {
         return;
      }

      $controller = $event->getController ();
      $bearer = $this->resolveBearerAttribute ($controller);

      if ($bearer === null) {
         return;
      }

      $request = $event->getRequest ();
      $token = $this->extractBearerToken ($request);

      // No token provided
      if ($token === null) {
         $this->flash->error ('Authentication required.', 'auth:missing');
         throw new UnauthorizedHttpException ('Bearer', 'Authentication required.');
      }

      // No decoder configured
      if ($this->decoder === null) {
         throw new \RuntimeException ('Bearer decoder not configured. Set wisp.bearer.decoder in config.');
      }

      // Decode and validate token
      $claims = $this->decoder->decode ($token);

      if ($claims === null) {
         $this->flash->error ('Invalid or expired token.', 'auth:invalid');
         throw new UnauthorizedHttpException ('Bearer', 'Invalid or expired token.');
      }

      // Validate required claims
      foreach ($bearer->claims as $key => $expectedValue) {
         if (!array_key_exists ($key, $claims)) {
            $this->flash->error ('Missing required claim.', 'auth:claim_missing');
            throw new UnauthorizedHttpException ('Bearer', 'Missing required claim.');
         }

         if ($claims [$key] !== $expectedValue) {
            $this->flash->error ('Insufficient permissions.', 'auth:claim_mismatch');
            throw new UnauthorizedHttpException ('Bearer', 'Insufficient permissions.');
         }
      }
   }

   private function resolveBearerAttribute (mixed $controller): ?Bearer
   {
      if (is_array ($controller)) {
         [ $instance, $method ] = $controller;
         return $this->findBearerAttribute ($instance::class, $method);
      }

      if (is_object ($controller) && method_exists ($controller, '__invoke')) {
         return $this->findBearerAttribute ($controller::class, '__invoke');
      }

      return null;
   }

   private function findBearerAttribute (string $class, string $method): ?Bearer
   {
      $methodReflection = new ReflectionMethod ($class, $method);
      $methodAttributes = $methodReflection->getAttributes (Bearer::class);

      if (!empty ($methodAttributes)) {
         return $methodAttributes [0]->newInstance ();
      }

      $classReflection = new ReflectionClass ($class);
      $classAttributes = $classReflection->getAttributes (Bearer::class);

      if (!empty ($classAttributes)) {
         return $classAttributes [0]->newInstance ();
      }

      return null;
   }

   private function extractBearerToken (mixed $request): ?string
   {
      $header = $request->headers->get ('Authorization', '');

      if (str_starts_with ($header, 'Bearer ')) {
         return substr ($header, 7);
      }

      return null;
   }
}
