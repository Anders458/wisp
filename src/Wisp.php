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
use Wisp\Listener\MiddlewareListener;
use Wisp\Http\Request;
use Wisp\Http\Response;
use Wisp\Service\Flash;

class Wisp
{
   public static ContainerBuilder $container;
   
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

      self::$container = new ContainerBuilder ();

      self::$container
         ->register (Runtime::class)
         ->setAutowired (true)
         ->setPublic (true)
         ->setArgument ('$root', $root)
         ->setArgument ('$stage', $stage)
         ->setArgument ('$debug', $debug)
         ->setArgument ('$version', $version);

      self::$container
         ->register (EventDispatcher::class)
         ->setPublic (true)
         ->setAutowired (true);

      self::$container
         ->register (Request::class)
         ->setSynthetic (true)
         ->setPublic (true);

      self::$container
         ->register (Response::class)
         ->setSynthetic (true)
         ->setPublic (true);

      self::$container
         ->register (Flash::class)
         ->setPublic (true)
         ->setAutowired (true);

      self::$container->set (Wisp::class, $this);
      self::$container->set (LoggerInterface::class, new Logger ($logs, $debug));

      $this->router = new Router ();
   }

   public static function container () : ContainerBuilder
   {
      return self::$container;
   }

   public function run () : void
   {
      self::$container->compile ();

      $request = Request::createFromGlobals ();
      $response = new Response ();

      self::$container->set (Request::class, $request);
      self::$container->set (Response::class, $response);

      $dispatcher = self::$container->get (EventDispatcher::class);
      
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

      $controllerResolver = new ContainerControllerResolver (self::$container);

      // $argumentResolver = new ArgumentResolver (
      //    new ArgumentMetadataFactory (),
      //    [
      //       new ServiceValueResolver (self::$container)
      //    ]
      // );

      $argumentResolver = new ArgumentResolver(
         new ArgumentMetadataFactory (),
         array_merge (
            [ new ServiceValueResolver (self::$container) ],
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

      self::$container->set (HttpKernelInterface::class, $kernel);

      $response = $kernel->handle ($request);
      $response->send ();

      $kernel->terminate ($request, $response);
   }

   public function __call (string $method, array $args) : mixed
   {
      return $this->router->$method (... $args);
   }
}