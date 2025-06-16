<?php

namespace Wisp;

use Exception;
use Wisp\Environment\Runtime;
use Wisp\Http\Request;
use Wisp\Http\Response;
use Wisp\Pipeline\Lifecycle;
use Wisp\Util\Logger;

class Router
{
   private RouteGroup $root;
   private array $routes;

   public function __construct ()
   {
      $this->root = new RouteGroup ($this, null);
      $this->routes = [];
   }

   public function dispatch (?Request $request = null) : Response
   {
      $container = Container::get ();

      $logger = $container->resolve (Logger::class);
      $runtime = $container->resolve (Runtime::class);

      if (!$request) {
         $request = new Request ();
      }

      $response = $request->response;

      $container
         ->bind (Request::class, fn () => $request)
         ->bind (Response::class, fn () => $response)
         ->bind (Router::class, fn () => $this);

      $queue = [];
      $queue [] = $this->root;

      $matches = [];

      $numRoutes = count ($this->routes);

      $logger->debug ('[Wisp] [Router] Router processing incoming request against {numRoutes} registered route' . ($numRoutes === 1 ? '' : 's'), [
         'request' => $request->toArray (),
         'numRoutes' => $numRoutes
      ]);

      while (!empty ($queue)) {
         $constraint = array_shift ($queue);

         $logger->debug ('[Wisp] [Router] Trying to match {type} with the following constraints: {constraints}', [
            'type' => get_class ($constraint),
            'constraints' => $constraint->toString ()
         ]);

         if (($params = $constraint->matches ($request)) !== false) {
            if ($constraint instanceof Route) {
               $logger->debug ('[Wisp] [Router] Route matched');

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
         $logger->debug ('[Wisp] [Router] No routes matched the request');
         $response->status (404);
      } else {
         $numMatches = count ($matches);

         $logger->debug ('[Wisp] [Router] Found {numMatches} matching route' . ($numMatches === 1 ? '' : 's'), [
            'numMatches' => $numMatches
         ]);

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
         $route->getActions (Lifecycle::before)
      );

      if ($action) {         
         $main = function (Response $response) use ($action) {
            if ($response->code >= 400) {
               return;
            }

            Util::runWithHooks (Invokable::from ($action));
         };

         $actions [] = Invokable::from ($main);
      }

      $actions = array_merge (
         $actions,
         $route->getActions (Lifecycle::after)
      );

      $numActions = count ($actions);

      $logger->debug ('[Wisp] [Router] Running lifecycle {numActions} action' . ($numActions === 1 ? '' : 's'), [
         'numActions' => $numActions
      ]);

      foreach ($actions as $index => $action) {
         if (!$response->sent) {
            try {
               $container->run ($action);
            } catch (Exception $e) {
               $logger->error ($e);
               $response->status (500);
            }
         } else {
            $logger->debug ('[Wisp] [Router] Lifecycle aborting early after {index} / {numActions} action' . ($index === 0 ? '' : 's') . '', [
               'index' => $index + 1,
               'numActions' => $numActions
            ]);
         }
      }

      $logger->debug ('[Wisp] [Router] Response generated after {responseTime} seconds', [
         'responseTime' => round ($runtime->elapsed (), 4),
         'response' => $response->toArray ()
      ]);

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
      $container = Container::get ();

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

   public function __call (string $method, array $args) : RouteGroup | Route
   {
      return $this->root->$method (... $args);
   }
}