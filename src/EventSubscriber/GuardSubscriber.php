<?php

namespace Wisp\EventSubscriber;

use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Wisp\Attribute\Can;
use Wisp\Attribute\Is;

class GuardSubscriber implements EventSubscriberInterface
{
   public function __construct (
      private AuthorizationCheckerInterface $authorizationChecker
   )
   {
   }

   public static function getSubscribedEvents (): array
   {
      return [
         KernelEvents::CONTROLLER => [ 'onController', 10 ]
      ];
   }

   public function onController (ControllerEvent $event): void
   {
      $controller = $event->getController ();

      if (is_array ($controller)) {
         [ $instance, $method ] = $controller;
         $this->checkGuards ($instance::class, $method);
      } elseif (is_object ($controller) && !$controller instanceof \Closure && method_exists ($controller, '__invoke')) {
         $this->checkGuards ($controller::class, '__invoke');
      }
   }

   private function checkGuards (string $class, string $method): void
   {
      $classReflection = new ReflectionClass ($class);
      $methodReflection = new ReflectionMethod ($class, $method);

      // Check class-level attributes first
      $this->checkIsAttributes ($classReflection->getAttributes (Is::class));
      $this->checkCanAttributes ($classReflection->getAttributes (Can::class));

      // Check method-level attributes (can override class-level)
      $this->checkIsAttributes ($methodReflection->getAttributes (Is::class));
      $this->checkCanAttributes ($methodReflection->getAttributes (Can::class));
   }

   /**
    * @param \ReflectionAttribute<Is>[] $attributes
    */
   private function checkIsAttributes (array $attributes): void
   {
      foreach ($attributes as $attribute) {
         $is = $attribute->newInstance ();

         foreach ($is->roles as $role) {
            // Convert shorthand 'admin' to 'ROLE_ADMIN' for Symfony's RoleVoter
            $symfonyRole = str_starts_with ($role, 'ROLE_') ? $role : 'ROLE_' . strtoupper ($role);

            if (!$this->authorizationChecker->isGranted ($symfonyRole)) {
               throw new AccessDeniedException (sprintf (
                  'Access denied. Required role: %s',
                  $role
               ));
            }
         }
      }
   }

   /**
    * @param \ReflectionAttribute<Can>[] $attributes
    */
   private function checkCanAttributes (array $attributes): void
   {
      foreach ($attributes as $attribute) {
         $can = $attribute->newInstance ();

         if (!$this->authorizationChecker->isGranted ($can->permission, $can->subject)) {
            throw new AccessDeniedException (sprintf (
               'Access denied. Required permission: %s',
               $can->permission
            ));
         }
      }
   }
}
