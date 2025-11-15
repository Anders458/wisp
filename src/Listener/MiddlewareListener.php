<?php

namespace Wisp\Listener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Wisp\Http\Request;
use Wisp\Http\Response;
use Wisp\Pipeline\Hook;
use Wisp\Route;
use Wisp\Router;

class MiddlewareListener implements EventSubscriberInterface
{
   private array $stack = [];

   public function __construct (
      private ArgumentResolverInterface $argumentResolver,
      private Router $router
   ) 
   {
   }

   public static function getSubscribedEvents () : array
   {
      return [
         // Run middleware pipeline after RouterListener (32) but before AuthorizationListener (16)
         KernelEvents::REQUEST => [ 'onRequest', 20 ],
         // Run controller hooks before controller execution
         KernelEvents::CONTROLLER_ARGUMENTS => [ 'onControllerArguments', 0 ],
         KernelEvents::RESPONSE => [ 'onAfter', 0 ]
      ];
   }

   public function onRequest (RequestEvent $event) : void
   {
      if (!$event->isMainRequest ()) {
         return;
      }

      $request = $event->getRequest ();
      $route = $this->getRoute ($request);

      if (!$route) {
         return;
      }

      // Execute middleware pipeline (includes authentication middleware)
      $response = $this->executePipeline ($route->getPipeline (Hook::Before), $request);

      if ($response) {
         // Middleware returned a response - stop processing and return it
         $event->setResponse ($response);
         $event->stopPropagation ();
      }
   }

   public function onControllerArguments (ControllerArgumentsEvent $event) : void
   {
      $request = $event->getRequest ();
      $controller = $event->getController ();

      array_push ($this->stack, $controller);

      // Execute controller before() hook
      $response = $this->executeControllerHook ($controller, Hook::Before->value, $request);

      if ($response) {
         $event->setController (fn () => $response);
      }
   }

   public function onAfter (ResponseEvent $event) : void
   {
      $request = $event->getRequest ();
      $response = $event->getResponse ();
      $route = $this->getRoute ($request);

      // Update container with current response so it can be injected
      // Response::class will resolve to this via alias
      container ()->set (SymfonyResponse::class, $response);

      $controller = array_pop ($this->stack);

      $result = $this->executeControllerHook ($controller, Hook::After->value, $request);

      if ($result instanceof Response) {
         $response = $result;
         container ()->set (SymfonyResponse::class, $response);
         $event->setResponse ($response);
      }

      if ($route) {
         $result = $this->executePipeline ($route->getPipeline (Hook::After), $request, $response);

         if ($result instanceof SymfonyResponse) {
            container ()->set (SymfonyResponse::class, $result);
            $event->setResponse ($result);
         }
      }
   }

   private function getRoute (Request $request) : ?Route
   {
      $name = $request->attributes->get ('_route');
      return $name ? $this->router->find ($name) : null;
   }

   private function executePipeline (array $handlers, Request $request, ?SymfonyResponse $response = null) : ?SymfonyResponse
   {
      foreach ($handlers as $handler) {
         $callable = ($handler->action) ($response);

         if (!$callable) {
            continue;
         }

         $arguments = $this->argumentResolver->getArguments ($request, $callable);
         $result = $callable (...$arguments);

         if ($result instanceof SymfonyResponse) {
            // Update container so next handler gets the updated response
            \Wisp\Wisp::container ()->set (SymfonyResponse::class, $result);
            return $result;
         }
      }

      return null;
   }

   private function executeControllerHook (mixed $controller, string $method, Request $request) : ?Response
   {
      if (!is_array ($controller) || !is_object ($controller [0])) {
         return null;
      }

      $instance = $controller [0];

      if (!method_exists ($instance, $method)) {
         return null;
      }

      $callable = [ $instance, $method ];

      $arguments = $this->argumentResolver->getArguments ($request, $callable);
      $result = $instance->$method (...$arguments);

      if ($result instanceof Response) {
         return $result;
      }

      return null;
   }
}

