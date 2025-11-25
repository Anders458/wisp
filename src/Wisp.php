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
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage as CurrentUserStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface as CurrentUserStorageInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager as VoterManager;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Strategy\AffirmativeStrategy as AnyVoterGrantsStrategy;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Csrf\TokenGenerator\UriSafeTokenGenerator as CsrfTokenGenerator;
use Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage as CsrfSessionStorage;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Wisp\Security\Voter\PermissionVoter;
use Wisp\Security\Voter\RoleVoter;
use Wisp\Session\CacheSessionStorage;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ContainerControllerResolver;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadataFactory;
use Symfony\Component\HttpKernel\EventListener\RouterListener;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Wisp\ArgumentResolver\ServiceValueResolver;
use Wisp\Environment\Runtime;
use Wisp\Environment\Stage;
use Wisp\Http\Request;
use Wisp\Http\Response;
use Wisp\Listener\AuthorizationListener;
use Wisp\Listener\ExceptionListener;
use Wisp\Listener\MiddlewareListener;
use Wisp\Middleware\ETag;
use Wisp\Security\CacheKeyValidator;
use Wisp\Security\CacheTokenProvider;
use Wisp\Contracts\KeyValidatorInterface;
use Wisp\Contracts\TokenProviderInterface;
use Wisp\Security\UserProvider\CacheUserProvider;
use Wisp\Environment\RuntimeInterface;
use Wisp\Service\Flash;
use Wisp\Service\FlashInterface;
use Wisp\Service\Keychain;
use Wisp\Service\KeychainInterface;

class Wisp
{
   public readonly EventDispatcherInterface  $dispatcher;
   public readonly HttpKernel                $kernel;
   public readonly Router                    $router;

   private string $root;
   private bool $debug;

   public function __construct (array $settings = [])
   {
      $name    = $settings ['name']    ?? 'Wisp';
      $this->root    = $settings ['root']    ?? getcwd ();
      $config  = $settings ['config']  ?? $this->root . '/config';
      $logs    = $settings ['logs']    ?? $this->root . '/logs';
      $stage   = $settings ['stage']   ?? Stage::production;
      $this->debug   = $settings ['debug']   ?? Stage::production !== $stage;
      $version = $settings ['version'] ?? '1.0.0';

      if ($this->debug) {
         Debug::enable ();
      }

      $container = Container::instance ();

      $container
         ->register (EventDispatcherInterface::class)
         ->setClass (EventDispatcher::class)
         ->setPublic (true);

      $container
         ->register (Router::class)
         ->setSynthetic (true)
         ->setPublic (true);

      $container
         ->register (RuntimeInterface::class)
         ->setClass (Runtime::class)
         ->setPublic (true)
         ->setArgument ('$root', $this->root)
         ->setArgument ('$stage', $stage)
         ->setArgument ('$debug', $this->debug)
         ->setArgument ('$version', $version);

      $container
         ->register (Request::class)
         ->setSynthetic (true)
         ->setPublic (true);

      $container
         ->register (RequestStack::class)
         ->setPublic (true);

      $container
         ->register (SymfonyResponse::class)
         ->setSynthetic (true)
         ->setPublic (true);

      $container
         ->register (AuthorizationListener::class)
         ->setPublic (true)
         ->setAutowired (true);

      $container
         ->register (ExceptionListener::class)
         ->setPublic (true)
         ->setAutowired (true);

      $container
         ->register (FlashInterface::class)
         ->setClass (Flash::class)
         ->setPublic (true)
         ->setAutowired (true);

      $container
         ->register (KeychainInterface::class)
         ->setClass (Keychain::class)
         ->setPublic (true)
         ->setAutowired (true)
         ->setArgument ('$path', $config);

      $container
         ->register (CacheItemPoolInterface::class)
         ->setClass (FilesystemAdapter::class)
         ->setArguments ([
            '$namespace' => $name,
            '$defaultLifetime' => 0,
            '$directory' => $this->root . '/var/cache'
         ])
         ->setPublic (true);

      $container
         ->register (LoggerInterface::class)
         ->setFactory ([ Logger::class, 'create' ])
         ->setPublic (true)
         ->setArgument ('$path', $logs)
         ->setArgument ('$debug', $this->debug);

      $container
         ->register (PropertyAccessorInterface::class)
         ->setFactory ([ PropertyAccess::class, 'createPropertyAccessor' ])
         ->setPublic (true);

      $container
         ->register (SerializerInterface::class)
         ->setFactory ([ SerializerFactory::class, 'create' ])
         ->setPublic (true);

      $container
         ->register (TranslatorInterface::class)
         ->setFactory ([ TranslatorFactory::class, 'create' ])
         ->setPublic (true)
         ->setArgument ('$localesDir', $this->root . '/i18n')
         ->setArgument ('$defaultLocale', 'en');

      $container
         ->register (ValidatorInterface::class)
         ->setFactory ([ ValidatorFactory::class, 'create' ])
         ->setPublic (true);

      $container
         ->register (CacheSessionStorage::class)
         ->setArguments ([
            '$cache' => new Reference (CacheItemPoolInterface::class),
            '$ttl' => 604800,
            '$name' => 'wisp',
            '$secure' => true,
            '$sameSite' => 'strict'
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
         ->register (AnyVoterGrantsStrategy::class)
         ->setArgument ('$allowIfAllAbstainDecisions', false)
         ->setPublic (true);

      $container
         ->register (VoterManager::class)
         ->setArguments ([
            '$voters' => [
               new RoleVoter (),
               new PermissionVoter ()
            ],
            '$strategy' => new Reference (AnyVoterGrantsStrategy::class)
         ])
         ->setPublic (true);

      $container
         ->register (AuthorizationCheckerInterface::class)
         ->setClass (AuthorizationChecker::class)
         ->setArguments ([
            '$tokenStorage' => new Reference (CurrentUserStorageInterface::class),
            '$accessDecisionManager' => new Reference (VoterManager::class)
         ])
         ->setPublic (true);

      $container
         ->register (CsrfSessionStorage::class)
         ->setArguments ([
            '$requestStack' => new Reference (RequestStack::class),
            '$namespace' => 'wisp:csrf'
         ])
         ->setPublic (true);

      $container
         ->register (CsrfTokenManagerInterface::class)
         ->setClass (CsrfTokenManager::class)
         ->setArguments ([
            '$generator' => new CsrfTokenGenerator (),
            '$storage' => new Reference (CsrfSessionStorage::class),
            '$namespace' => null
         ])
         ->setPublic (true);

      $container
         ->register ('password_hasher.factory', PasswordHasherFactory::class)
         ->setArguments ([[
            'common' => [ 'algorithm' => 'auto' ]
         ]])
         ->setPublic (false);

      $container
         ->register (PasswordHasherInterface::class)
         ->setFactory ([new Reference ('password_hasher.factory'), 'getPasswordHasher'])
         ->setArguments ([ 'common' ])
         ->setPublic (true);

      $container
         ->register (CurrentUserStorageInterface::class)
         ->setClass (CurrentUserStorage::class)
         ->setPublic (true);

      $container
         ->register (TokenProviderInterface::class)
         ->setClass (CacheTokenProvider::class)
         ->setPublic (true)
         ->setArgument ('$cache', new Reference (CacheItemPoolInterface::class));

      $container
         ->register (KeyValidatorInterface::class)
         ->setClass (CacheKeyValidator::class)
         ->setPublic (true)
         ->setArgument ('$cache', new Reference (CacheItemPoolInterface::class));

      $container
         ->register (CacheUserProvider::class)
         ->setPublic (true);

      $container->setAlias (EventDispatcher::class, EventDispatcherInterface::class);
      $container->setAlias (Flash::class, FlashInterface::class);
      $container->setAlias (Keychain::class, KeychainInterface::class);
      $container->setAlias (Response::class, SymfonyResponse::class);
      $container->setAlias (Runtime::class, RuntimeInterface::class);

      $this->router = new Router ();

      $this->middleware (ETag::class);
   }

   public static function container () : ContainerBuilder
   {
      return Container::instance ();
   }

   public function initialize () : void
   {
      if (isset ($this->kernel)) {
         return;
      }

      $cacheDir = $this->root . '/var/cache/routes';
      $this->router->setCacheDir ($cacheDir);
      $this->router->setDebug ($this->debug);

      if (!$this->debug && $this->router->isCacheValid ()) {
         $this->router->loadFromCache ();
      }

      $container = Container::instance ();

      $container->compile ();

      $container->set (Wisp::class, $this);
      $container->set (Router::class, $this->router);

      if (!$this->debug && !$this->router->isCacheValid ()) {
         $this->router->warmup ();
      }

      $this->dispatcher = $container->get (EventDispatcherInterface::class);

      $requestStack = $container->get (RequestStack::class);

      $this->dispatcher->addSubscriber (
         $container->get (ExceptionListener::class)
      );

      $this->dispatcher->addSubscriber (
         $container->get (AuthorizationListener::class)
      );

      $controllerResolver = new ContainerControllerResolver ($container);

      $argumentResolver = new ArgumentResolver(
         new ArgumentMetadataFactory (),
         array_merge (
            [ new ServiceValueResolver (Container::instance ()) ],
            ArgumentResolver::getDefaultArgumentValueResolvers (),
         )
      );

      $this->dispatcher->addSubscriber (
         new MiddlewareListener (
            $argumentResolver,
            $this->router
         )
      );

      $this->kernel = new HttpKernel (
         $this->dispatcher,
         $controllerResolver,
         null,
         $argumentResolver
      );

      $container->set (HttpKernelInterface::class, $this->kernel);
   }

   public function handleRequest (Request $request) : SymfonyResponse
   {
      $this->initialize ();

      $container = Container::instance ();

      $response = new Response ();

      $container->set (Request::class, $request);
      $container->set (SymfonyResponse::class, $response);
      $container->set (Response::class, $response);

      $context = (new RequestContext ())
         ->fromRequest ($request);

      $matcher = new UrlMatcher ($this->router->routes, $context);

      $requestStack = $container->get (RequestStack::class);
      $requestStack->push ($request);

      $this->dispatcher->addSubscriber (
         new RouterListener (
            $matcher,
            $requestStack
         )
      );

      return $this->kernel->handle ($request);
   }

   public function run () : void
   {
      $request = Request::createFromGlobals ();
      $response = $this->handleRequest ($request);

      $response->send ();

      $this->kernel->terminate ($request, $response);
   }

   public function __call (string $method, array $args) : mixed
   {
      return $this->router->$method (... $args);
   }
}
