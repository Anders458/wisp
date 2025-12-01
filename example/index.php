<?php

require 'vendor/autoload.php';

use Example\Controller\Gateway\CookieController;
use Example\Controller\Gateway\TokenController;
use Example\Controller\UserController;
use Example\Security\DatabaseUserProvider;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\Session\Session as SymfonySession;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Wisp\Console\Console;
use Wisp\Environment\Runtime;
use Wisp\Environment\Stage;
use Wisp\Example\Controller\ErrorController;
use Wisp\Example\Controller\ExamplesController;
use Wisp\Example\Controller\SystemController;
use Wisp\Http\Request;
use Wisp\Http\Response;
use Wisp\Middleware\Authentication\CookieAuthentication;
use Wisp\Middleware\Authentication\TokenAuthentication;
use Wisp\Middleware\CORS;
use Wisp\Middleware\CSRF;
use Wisp\Middleware\Envelope;
use Wisp\Middleware\Helmet;
use Wisp\Middleware\Session;
use Wisp\Middleware\Throttle;
use Wisp\Contracts\UserProviderInterface;
use Wisp\Service\KeychainInterface;
use Wisp\Session\CacheSessionStorage;
use Wisp\Wisp;

$runtime = Runtime::configure ()
   ->root (__DIR__)
   ->version ('1.0.0')
   ->stage (Stage::development)
   ->debug (true)
   ->detectStageFromCli ()
   ->detectDebugFromCli ()
   ->allowDebugFromQuery ('secret')
   ->allowDebugInStages ([ Stage::development, Stage::staging ])
   ->build ();

$app = new Wisp ($runtime, [
   'name' => 'Wisp'
]);

Wisp::container ()
   ->register (UserProviderInterface::class)
   ->setClass (DatabaseUserProvider::class)
   ->setPublic (true);

Wisp::container ()
   ->register (CacheSessionStorage::class)
   ->setArguments ([
      '$cache' => new Reference (CacheItemPoolInterface::class),
      '$ttl' => 604800,
      '$name' => 'wisp',
      '$secure' => false,
      '$sameSite' => 'lax'
   ])
   ->setPublic (true);

Wisp::container ()
   ->register (SessionInterface::class)
   ->setClass (SymfonySession::class)
   ->setArguments ([
      '$storage' => new Reference (CacheSessionStorage::class)
   ])
   ->setPublic (true);

$app
   ->on (404, [ ErrorController::class, 'notFound' ])
   ->on (500, [ ErrorController::class, 'internalError' ])

   ->middleware (Session::class)
   ->middleware (CORS::class)
   ->middleware (Helmet::class)
   ->middleware (Envelope::class)

   ->middleware (CookieAuthentication::class)
   ->middleware (TokenAuthentication::class)

   ->before (function () {
      $config   = container (KeychainInterface::class)->get ('config');
      $settings = container (KeychainInterface::class)->get ('settings');
   })

   ->before (function (LoggerInterface $logger, Request $request) {
      $logger->info ('Global before', [
         'method' => $request->getMethod (),
         'uri' => $request->getRequestUri ()
      ]);
   })

   ->after (function (LoggerInterface $logger) {
      $logger->debug ('Global after');
   })

   ->group ('/v1', fn ($group) =>
      $group
         ->middleware (Throttle::class, [
            'limit' => 1000,
            'interval' => 10
         ])

         ->get ('/csrf', function (CSRF $csrf) {
            return (new Response ())->json ([
               'token' => $csrf->getToken ()
            ]);
         })

         ->get ('/health-check', [ SystemController::class, 'healthCheck' ])

         ->group ('/gateway', fn ($group) =>
            $group
               ->post ('/tokens/login',   [ TokenController::class, 'login' ])
               ->post ('/tokens/refresh', [ TokenController::class, 'refresh' ])
               ->post ('/tokens/logout',  [ TokenController::class, 'logout' ])

               ->post ('/cookie/login',  [ CookieController::class, 'login' ])
               ->post ('/cookie/logout', [ CookieController::class, 'logout' ])
         )

         ->group ('/examples', fn ($group) =>
            $group
               ->get ('/redirect', [ ExamplesController::class, 'redirect' ])
               ->get ('/download', [ ExamplesController::class, 'download' ])
               ->get ('/html',     [ ExamplesController::class, 'html' ])
               ->get ('/text',     [ ExamplesController::class, 'text' ])
               ->post ('/form',    [ ExamplesController::class, 'form' ])
               ->post ('/validation', [ ExamplesController::class, 'validation' ])

               ->get ('/session-test', function (SessionInterface $session) {
                  $counter = $session->get ('counter', 0);
                  $session->set ('counter', $counter + 1);

                  return (new Response ())->json ([
                     'counter' => $counter + 1,
                     'message' => __ ('examples.session_counter_message')
                  ]);
               })
         )

         ->get ('/users/@me', [ UserController::class, 'me' ])
            ->is ('user')
   );

if (realpath ($_SERVER ['SCRIPT_FILENAME'] ?? '') === realpath (__FILE__)) {
   if ($runtime->isCli ()) {
      exit (
         (new Console ($app, 'Wisp Console', '1.0.0'))
            ->registerFrameworkCommands ()
            ->discoverCommands ('Example\\Command')
            ->run ()
      );
   }

   $app->run ();
}

return $app;