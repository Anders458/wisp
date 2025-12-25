<?php

namespace Wisp\EventSubscriber;

use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Wisp\Attribute\After;
use Wisp\Attribute\Before;
use Wisp\Http\Request;

class HookSubscriber implements EventSubscriberInterface
{
   private ?array $pendingAfterHooks = null;
   private ?object $controllerInstance = null;
   private ?string $actionMethod = null;

   public function __construct (
      private ContainerInterface $container
   )
   {
   }

   public static function getSubscribedEvents (): array
   {
      return [
         KernelEvents::CONTROLLER => [ 'onController', 5 ],
         KernelEvents::RESPONSE => [ 'onResponse', -5 ]
      ];
   }

   public function onController (ControllerEvent $event): void
   {
      if (!$event->isMainRequest ()) {
         return;
      }

      $controller = $event->getController ();

      if (!is_array ($controller)) {
         return;
      }

      [ $instance, $method ] = $controller;
      $this->controllerInstance = $instance;
      $this->actionMethod = $method;

      $symfonyRequest = $event->getRequest ();
      $wispRequest = $this->getWispRequest ($symfonyRequest);
      $beforeHooks = $this->findBeforeHooks ($instance::class, $method);

      foreach ($beforeHooks as $hookMethod) {
         $result = $instance->$hookMethod ($wispRequest);

         if ($result instanceof Response) {
            $event->setController (fn () => $result);
            return;
         }
      }

      // Copy any attributes set by hooks back to the original request
      foreach ($wispRequest->attributes->all () as $key => $value) {
         $symfonyRequest->attributes->set ($key, $value);
      }

      // Store after hooks for later
      $this->pendingAfterHooks = $this->findAfterHooks ($instance::class, $method);
   }

   public function onResponse (ResponseEvent $event): void
   {
      if ($this->pendingAfterHooks === null || $this->controllerInstance === null) {
         return;
      }

      $request = $this->getWispRequest ($event->getRequest ());
      $response = $event->getResponse ();

      foreach ($this->pendingAfterHooks as $hookMethod) {
         $result = $this->controllerInstance->$hookMethod ($request, $response);

         if ($result instanceof Response) {
            $response = $result;
         }
      }

      $event->setResponse ($response);

      // Clean up
      $this->pendingAfterHooks = null;
      $this->controllerInstance = null;
      $this->actionMethod = null;
   }

   /**
    * @return string[] Method names with #[Before] that apply to the action
    */
   private function findBeforeHooks (string $class, string $actionMethod): array
   {
      return $this->findHooks ($class, $actionMethod, Before::class);
   }

   /**
    * @return string[] Method names with #[After] that apply to the action
    */
   private function findAfterHooks (string $class, string $actionMethod): array
   {
      return $this->findHooks ($class, $actionMethod, After::class);
   }

   /**
    * @param class-string<Before|After> $attributeClass
    * @return string[]
    */
   private function findHooks (string $class, string $actionMethod, string $attributeClass): array
   {
      $hooks = [];
      $reflection = new ReflectionClass ($class);

      // Walk up the class hierarchy (parent hooks run first)
      $classChain = [];

      while ($reflection) {
         $classChain [] = $reflection;
         $reflection = $reflection->getParentClass ();
      }

      // Reverse so parent hooks come first
      $classChain = array_reverse ($classChain);

      foreach ($classChain as $classReflection) {
         foreach ($classReflection->getMethods (ReflectionMethod::IS_PUBLIC) as $method) {
            // Skip the action method itself
            if ($method->getName () === $actionMethod) {
               continue;
            }

            $attributes = $method->getAttributes ($attributeClass);

            foreach ($attributes as $attr) {
               $hook = $attr->newInstance ();

               if ($hook->appliesTo ($actionMethod)) {
                  $hooks [] = $method->getName ();
               }
            }
         }
      }

      return array_unique ($hooks);
   }

   private function getWispRequest (\Symfony\Component\HttpFoundation\Request $symfonyRequest): Request
   {
      if ($symfonyRequest instanceof Request) {
         return $symfonyRequest;
      }

      return Request::createFrom ($symfonyRequest);
   }
}
