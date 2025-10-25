<?php

namespace Wisp;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ContainerControllerResolver;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadataFactory;
use Symfony\Component\HttpKernel\EventListener\RouterListener;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Wisp\ArgumentResolver\ServiceValueResolver;
use Wisp\Environment\Runtime;
use Wisp\Environment\Stage;
use Wisp\Http\Request;
use Wisp\Http\Response;
use Wisp\Listener\MiddlewareListener;
use Wisp\Service\Flash;

class Wisp
{
   public readonly EventDispatcher  $dispatcher;
   public readonly HttpKernel       $kernel;
   public readonly Router           $router;

   public function __construct (array $settings = [])
   {
      $name    = $settings ['name']    ?? 'Wisp';
      $root    = $settings ['root']    ?? getcwd ();
      $logs    = $settings ['logs']    ?? $root . '/logs';
      $stage   = $settings ['stage']   ?? Stage::production;
      $debug   = $settings ['debug']   ?? Stage::production !== $stage;
      $version = $settings ['version'] ?? '1.0.0';

      if ($debug) {
         Debug::enable ();
      }

      Container::instance ()
         ->register (Runtime::class)
         ->setAutowired (true)
         ->setPublic (true)
         ->setArgument ('$root', $root)
         ->setArgument ('$stage', $stage)
         ->setArgument ('$debug', $debug)
         ->setArgument ('$version', $version);

      Container::instance ()
         ->register (EventDispatcher::class)
         ->setPublic (true)
         ->setAutowired (true);

      Container::instance ()
         ->register (Request::class)
         ->setSynthetic (true)
         ->setPublic (true);

      Container::instance ()
         ->register (Response::class)
         ->setSynthetic (true)
         ->setPublic (true);

      Container::instance ()
         ->register (Flash::class)
         ->setPublic (true)
         ->setAutowired (true);

      Container::instance ()
         ->register (LoggerInterface::class)
         ->setFactory ([ Logger::class, 'create' ])
         ->setPublic (true)
         ->setArgument ('$path', $logs)
         ->setArgument ('$debug', $debug);

      Container::instance ()->set (Wisp::class, $this);

      $this->router = new Router ();
   }

   public static function container () : ContainerBuilder
   {
      return Container::instance ();
   }

   public function run () : void
   {
      Container::instance ()->compile ();

      $request = Request::createFromGlobals ();
      $response = new Response ();

      Container::instance ()->set (Request::class, $request);
      Container::instance ()->set (Response::class, $response);

      $dispatcher = Container::instance ()->get (EventDispatcher::class);
      
      $context = (new RequestContext ())
         ->fromRequest ($request);
      
         $matcher = new UrlMatcher ($this->router->routes, $context);

      $requestStack = new RequestStack ();

      $dispatcher->addSubscriber (
         new RouterListener (
            $matcher,
            $requestStack
         )
      );

      $controllerResolver = new ContainerControllerResolver (Container::instance ());

      // $argumentResolver = new ArgumentResolver (
      //    new ArgumentMetadataFactory (),
      //    [
      //       new ServiceValueResolver (Container::instance ())
      //    ]
      // );

      $argumentResolver = new ArgumentResolver(
         new ArgumentMetadataFactory (),
         array_merge (
            [ new ServiceValueResolver (Container::instance ()) ],
            ArgumentResolver::getDefaultArgumentValueResolvers (),
         )
      );

      $dispatcher->addSubscriber (
         new MiddlewareListener (
            $argumentResolver,
            $this->router
         )
      );

      $kernel = new HttpKernel (
         $dispatcher,
         $controllerResolver,
         null,
         $argumentResolver
      );

      Container::instance ()->set (HttpKernelInterface::class, $kernel);

      $response = $kernel->handle ($request);
      $response->send ();

      $kernel->terminate ($request, $response);
   }

   public function __call (string $method, array $args) : mixed
   {
      return $this->router->$method (... $args);
   }
}