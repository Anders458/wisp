<?php

namespace Wisp;

use Exception;
use Wisp\Http\Request;
use Wisp\Http\Response;
use Wisp\Pipeline\Stage;

class Router
{
   private RouteGroup $root;
   private array $routes;

   public function __construct ()
   {
      $this->root = new RouteGroup ($this, null, '');
      $this->routes = [];
   }

   public function dispatch (?Request $request = null) : Response
   {
      $container = Wisp::container ();

      if (!$request) {
         $request = new Request ();
      }

      $response = $request->response;

      $container->bind (Container::class, fn () => $container);
      $container->bind (Request::class, fn () => $request);
      $container->bind (Response::class, fn () => $response);
      $container->bind (Router::class, fn () => $this);

      $queue = [];
      $queue [] = $this->root;

      $matches = [];

      while (!empty ($queue)) {
         $constraint = array_shift ($queue);

         if (($params = $constraint->matches ($request)) !== false) {
            if ($constraint instanceof Route) {
               $matches [] = [
                  'route'  => $constraint,
                  'params' => $params
               ];
            } else if ($constraint instanceof RouteGroup) {
               $queue = array_merge (
                  $queue,
                  $constraint->getRoutes (),
                  $constraint->getGroups ()
               );
            }
         }
      }

      $route = $this->root;
      
      $action = null;
      $actions = [];

      if (empty ($matches)) {
         $response->status (404);
      } else {
         usort (
            $matches, 
            fn ($matchX, $matchY) => 
               $matchY ['route']->getPriority () <=> $matchX ['route']->getPriority ()
         );

         $match = $matches [0];

         $request->route = $match ['route'];
         $request->params = $match ['params'];

         $route = $match ['route'];
         $action = $match ['route']->getAction ();
      }

      $actions = array_merge (
         $actions,
         $route->getActions (Stage::before)
      );

      if ($action) {         
         $main = function (Response $response) use ($container, $action) {
            if ($response->code >= 400) {
               return;
            }

            Util::runWithHooks (Invokable::from ($action));
         };

         $actions [] = Invokable::from ($main);
      }

      $actions = array_merge (
         $actions,
         $route->getActions (Stage::after)
      );

      foreach ($actions as $action) {
         if (!$response->sent) {
            try {
               $container->run ($action);
            } catch (Exception $e) {
               $response->status (500);
            }
         }
      }

      if (!$response->sent) {       
         $response->send ();
      }

      return $response;
   }

   public function domain (string $host, callable $callback) : self
   {
      $this->root->group ('', function ($group) use ($host, $callback) {
         $group->host ($host);
         $callback ($group);
      });

      return $this;
   }

   public function forward (string $to) : void
   {
      $container = Wisp::container ();

      $container->resolve (Request::class)->forwarding = true;

      if (! ($route = $this->getRoute ($to))) {
         throw new Exception ('Forwarding route not found: ' . $to);
      }

      // Todo: Run all middleware that is a descendant whenever the route branches from
      // the originally matched route.

      Util::runWithHooks (Invokable::from ($route->getAction ()));
   }

   public function getRoute (string $name) : ?Route
   {
      if (!isset ($this->routes [$name])) {
         return null;
      }

      return $this->routes [$name];
   }

   public function getRoutes () : array
   {
      return $this->routes;
   }

   public function register (Route $route) : self
   {
      $this->routes [$route->getName ()] = $route;
      return $this;
   }

   public function __call (string $method, array $args) : self
   {
      $this->root->$method (... $args);
      return $this;
   }
}