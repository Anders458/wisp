<?php

require 'vendor/autoload.php';

// use Symfony\Component\DependencyInjection\ContainerBuilder;
// use Symfony\Component\DependencyInjection\ContainerInterface;
// use Symfony\Component\EventDispatcher\EventDispatcher;
// use Symfony\Component\HttpFoundation\Request;
// use Symfony\Component\HttpFoundation\Response;
// use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
// use Symfony\Component\HttpKernel\Controller\ContainerControllerResolver;
// use Symfony\Component\HttpKernel\HttpKernel;
// use Symfony\Component\Routing\Route;
// use Symfony\Component\Routing\RouteCollection;
// use Symfony\Component\Routing\RequestContext;
// use Symfony\Component\Routing\Matcher\UrlMatcher;

// require __DIR__ . '/vendor/autoload.php';

// /**
//  * Custom controller resolver that always uses the DI container.
//  */
// class ContainerControllerResolver extends ControllerResolver
// {
//     private ContainerInterface $container;

//     public function __construct(ContainerInterface $container)
//     {
//         parent::__construct();
//         $this->container = $container;
//     }

//     public function getController(Request $request): callable|false
//     {
//         $controller = $request->attributes->get('_controller');

//         // Case 1: [ControllerClass::class, 'method']
//         if (is_array($controller) && is_string($controller[0])) {
//             $instance = $this->container->get($controller[0]);
//             return [$instance, $controller[1]];
//         }

//         // Case 2: "ControllerClass::method"
//         if (is_string($controller) && str_contains($controller, '::')) {
//             [$class, $method] = explode('::', $controller, 2);
//             $instance = $this->container->get($class);
//             return [$instance, $method];
//         }

//         // Case 3: Closure or callable
//         if (is_callable($controller)) {
//             return $controller;
//         }

//         return parent::getController($request);
//     }
// }

/**
 * Minimal App wrapper.
 */
// class App
// {
//     private RouteCollection $routes;
//     private ContainerBuilder $container;

//     public function __construct()
//     {
//         $this->routes = new RouteCollection();
//         $this->container = new ContainerBuilder();

        // Make the Request service public so it isn't inlined
//         $this->container
//             ->register(Request::class, Request::class)
//             ->setFactory([Request::class, 'createFromGlobals'])
//             ->setPublic(true);

//         $this->container
//             ->register(Response::class, Response::class)
//             ->setShared(true)
//             ->setAutowired (true)
//             ->setPublic(true);
//     }

//     public function get(string $path, callable|array|string $handler): void
//     {
//         $this->routes->add(uniqid('route_'), new Route(
//             $path,
//             ['_controller' => $handler],
//             [], [], '', [], ['GET']
//         ));
//     }

//     public function post(string $path, callable|array|string $handler): void
//     {
//         $this->routes->add(uniqid('route_'), new Route(
//             $path,
//             ['_controller' => $handler],
//             [], [], '', [], ['POST']
//         ));
//     }

//     public function register(string $id, string $class): void
//     {
//         $this->container
//             ->register($id, $class)
//             ->setAutowired(true)
//             ->setPublic(true);
//     }

//     public function run(): void
//     {
//         // Compile AFTER all service registrations
//         $this->container->compile(true);

//         // Retrieve the global request from the compiled container
//         $request = $this->container->get(Request::class);

//         $context = (new RequestContext())->fromRequest($request);
//         $matcher = new UrlMatcher($this->routes, $context);

//         $controllerResolver = new ContainerControllerResolver($this->container);
//         $argumentResolver = new ArgumentResolver();
//         $eventDispatcher = new EventDispatcher();

//         $kernel = new HttpKernel(
//             $eventDispatcher,
//             $controllerResolver,
//             null,
//             $argumentResolver
//         );

//         try {
//             $request->attributes->add($matcher->match($request->getPathInfo()));
//             $response = $kernel->handle($request);
//         } catch (\Symfony\Component\Routing\Exception\ResourceNotFoundException) {
//             $response = new Response('Not Found', 404);
//         } catch (\Throwable $e) {
//             $response = new Response('Error: ' . $e->getMessage(), 500);
//         }

//         $response->send();
//     }
// }

// /**
//  * Example services & controllers
//  */
// class SomeService
// {
//     public function getName(): string
//     {
//         return 'Symfony World';
//     }
// }

// class HelloController
// {
//     private SomeService $service;

//     public function __construct(SomeService $service)
//     {
//         $this->service = $service;
//     }

//     public function index(Request $request): Response
//     {
//         return new Response('Hello ' . $this->service->getName());
//     }

//     public function greet(Request $request): Response
//     {
//         $name = $request->query->get('name', 'Stranger');
//         return new Response("Hello $name, from controller!");
//     }
// }

// /**
//  * Bootstrapping
//  */
// $app = new App();
// $app->register(SomeService::class, SomeService::class);
// $app->register(HelloController::class, HelloController::class);

// $app->get('/', [HelloController::class, 'index']);
// $app->get('/greet', [HelloController::class, 'greet']);
// $app->get('/hello/world', function (Request $r, Response $x) {
//    var_dump ($r);
//    die ();
//    // new Response('Hello from closure!')
// });

// $app->run();

// die ();

use Symfony\Component\EventDispatcher\EventDispatcher;
use Wisp\Environment\Stage;
use Wisp\Example\Controller\System;
use Wisp\Example\Controller\Error;
use Wisp\Example\Middleware\Auth;
use Wisp\Example\Middleware\Envelope;
use Wisp\Http\Request;
use Wisp\Http\Response;
use Wisp\Wisp;

$app = new Wisp (
   [
      'name'    => 'Wisp', 
      'root'    => __DIR__,
      'debug'   => true,
      'stage'   => Stage::development,
      'version' => '1.0.0',
   ]
);

$app
   
   ->on (200, [ Error::class, 'notFound' ])
   // ->on (404, [ Error::class, 'notFound' ])
   // ->on (500, [ Error::class, 'internalError' ])
   
   ->middleware (Envelope::class, [ 'settings!' ])
   
   ->before (function (Request $request) {
      error_log ('Before (root)');
      // var_dump ($request);
      // die ('t1');
      // $logger->info ('({ip}) {method} {url}', [
      //    'ip'     => $request->ip (),
      //    'method' => $request->method,
      //    'url'    => $request->url->toString ()
      // ])
   })

   // ->middleware (Auth::class)

   ->group ('/v1', fn ($group) => 
      $group
         ->before (fn () => error_log ('Before /v1 (1)'))
         ->before (fn () => error_log ('Before /v1 (2)'))
         ->after (fn () => error_log ('After /v1'))
         ->get ('/health-check', [ System::class, 'healthCheck' ])
            ->before (fn () => error_log ('Before /health-check'))
            ->after (fn () => error_log ('After /health-check'))
         ->get ('/forward', [ System::class, 'forward' ])
            ->before (fn () => error_log ('Before /forward'))
            ->after (fn () => error_log ('After /forward'))
   )
   
   ->get ('/hello/{name}', function (Request $request, string $name) {
      return (new Response ())
         ->status (200)
         ->body ('Hello, ' . $name);
   });
   
$app->run ();

// $app->container
//    ->bind ('db', fn () => true)
//    ->bind ('ses', fn () => true)
//    ->bind ('view', fn () => true);

// $routes = new RouteCollection();
// $routes->add('hello', new Route('/hello/{name}', [
//    '_controller' => function (Request $request): Response {
//       return new Response(
//          printf("Hello %s", $request->get('name'))
//       );
//    }]
// ));

// $request = Request::createFromGlobals();

// $matcher = new UrlMatcher (
//    $routes, new RequestContext ()
// );

// $dispatcher = new EventDispatcher ();

// $dispatcher->addSubscriber (
//    new RouterListener ($matcher, new RequestStack ()
// ));

// $controllerResolver = new ControllerResolver ();
// $argumentResolver = new ArgumentResolver ();

// $kernel = new HttpKernel (
//    $dispatcher, 
//    $controllerResolver, 
//    new RequestStack (), 
//    $argumentResolver
// );

// $response = $kernel->handle ($request);
// $response->send ();

// $kernel->terminate ($request, $response);