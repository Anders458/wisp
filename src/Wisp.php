<?php

namespace Wisp;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\ErrorHandler\Debug;
use Wisp\Session\CacheSessionStorage;
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
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Wisp\ArgumentResolver\ServiceValueResolver;
use Wisp\Environment\Runtime;
use Wisp\Environment\Stage;
use Wisp\Http\Request;
use Wisp\Http\Response;
use Wisp\Listener\MiddlewareListener;
use Wisp\Middleware\ETag;
use Wisp\Service\Flash;
use Wisp\Service\Keychain;

class Wisp
{
   public readonly EventDispatcher  $dispatcher;
   public readonly HttpKernel       $kernel;
   public readonly Router           $router;

   public function __construct (array $settings = [])
   {
      $name    = $settings ['name']    ?? 'Wisp';
      $root    = $settings ['root']    ?? getcwd ();
      $config  = $settings ['config']  ?? $root . '/config';
      $logs    = $settings ['logs']    ?? $root . '/logs';
      $stage   = $settings ['stage']   ?? Stage::production;
      $debug   = $settings ['debug']   ?? Stage::production !== $stage;
      $version = $settings ['version'] ?? '1.0.0';

      if ($debug) {
         Debug::enable ();
      }

      $container = Container::instance ();

      $container
         ->register (Runtime::class)
         ->setPublic (true)
         ->setArgument ('$root', $root)
         ->setArgument ('$stage', $stage)
         ->setArgument ('$debug', $debug)
         ->setArgument ('$version', $version);

      $container
         ->register (EventDispatcher::class)
         ->setPublic (true);

      $container
         ->register (Request::class)
         ->setSynthetic (true)
         ->setPublic (true);

      $container
         ->register (Response::class)
         ->setSynthetic (true)
         ->setPublic (true);

      $container
         ->register (Flash::class)
         ->setPublic (true);

      $container
         ->register (Keychain::class)
         ->setPublic (true)
         ->setAutowired (true)
         ->setArgument ('$path', $config);

      $container
         ->register (CacheItemPoolInterface::class)
         ->setClass (FilesystemAdapter::class)
         ->setArguments ([
            '$namespace' => $name,
            '$defaultLifetime' => 0,
            '$directory' => null
         ])
         ->setPublic (true);

      $container
         ->register (CacheSessionStorage::class)
         ->setArguments ([
            '$cache' => new Reference (CacheItemPoolInterface::class),
            '$ttl' => 604800 // 7 days
         ])
         ->setPublic (true);

      $container
         ->register (SessionInterface::class)
         ->setClass (Session::class)
         ->setArguments ([
            '$storage' => new Reference (CacheSessionStorage::class)
         ])
         ->setPublic (true);

      $container
         ->register (ValidatorInterface::class)
         ->setFactory ([ ValidatorFactory::class, 'create' ])
         ->setPublic (true);

      $container
         ->register (LoggerInterface::class)
         ->setFactory ([ Logger::class, 'create' ])
         ->setPublic (true)
         ->setArgument ('$path', $logs)
         ->setArgument ('$debug', $debug);

      $container->set (Wisp::class, $this);

      $this->router = new Router ();

      $this->middleware (ETag::class);
   }

   public static function container () : ContainerBuilder
   {
      return Container::instance ();
   }

   public function run () : void
   {
      $container = Container::instance ();

      $container->compile ();

      $request = Request::createFromGlobals ();
      $response = new Response ();

      $container->set (Request::class, $request);
      $container->set (Response::class, $response);

      $dispatcher = $container->get (EventDispatcher::class);
      
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

      $controllerResolver = new ContainerControllerResolver ($container);

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

      $container->set (HttpKernelInterface::class, $kernel);

      $response = $kernel->handle ($request);
      $response->send ();

      $kernel->terminate ($request, $response);
   }

   public function __call (string $method, array $args) : mixed
   {
      return $this->router->$method (... $args);
   }
}