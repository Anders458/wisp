<?php

require 'vendor/autoload.php';

use Example\Controller\Gateway\CookieController;
use Example\Controller\Gateway\TokenController;
use Example\Controller\UserController;
use Example\Security\DatabaseUserProvider;
use Psr\Log\LoggerInterface;
use Wisp\Environment\Stage;
use Wisp\Example\Controller\ErrorController;
use Wisp\Example\Controller\ExamplesController;
use Wisp\Example\Controller\HeroesController;
use Wisp\Example\Controller\SystemController;
use Wisp\Http\Request;
use Wisp\Http\Response;
use Wisp\Middleware\Authentication\TokenAuthentication;
use Wisp\Middleware\CORS;
use Wisp\Middleware\CSRF;
use Wisp\Middleware\Envelope;
use Wisp\Middleware\Helmet;
use Wisp\Middleware\Session;
use Wisp\Middleware\Throttle;
use Wisp\Security\Contracts\UserProviderInterface;
use Wisp\Service\KeychainInterface;
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

Wisp::container ()
   ->register (UserProviderInterface::class)
   ->setClass (DatabaseUserProvider::class)
   ->setPublic (true);

$app
   ->on (404, [ ErrorController::class, 'notFound' ])
   ->on (500, [ ErrorController::class, 'internalError' ])

   ->middleware (Session::class, [
      'cookieName' => 'wisp_session',  // Remove __Host- prefix for localhost testing
      'secure' => false                 // Allow non-HTTPS for development
   ])
   ->middleware (CORS::class)
   ->middleware (Helmet::class)
   ->middleware (Envelope::class)

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

   ->group ('/examples', fn ($group) =>
      $group
         ->get ('/redirect', [ ExamplesController::class, 'redirect' ])
         ->get ('/download', [ ExamplesController::class, 'download' ])
         ->get ('/html',     [ ExamplesController::class, 'html' ])
         ->get ('/text',     [ ExamplesController::class, 'text' ])
         ->post ('/form',    [ ExamplesController::class, 'form' ])
            ->middleware (CSRF::class)
   )

   ->group ('/v1', fn ($group) =>
      $group
         ->middleware (Throttle::class, [
            'limit' => 1000,
            'window' => 10
         ])

         // Apply CookieAuthentication globally to enable cookie-based auth everywhere
         ->middleware (Wisp\Middleware\Authentication\CookieAuthentication::class)

         ->get ('/csrf', function (CSRF $csrf) {
            return (new Response ())->json ([
               'token' => $csrf->getToken ()
            ]);
         })

         ->get ('/health-check', [ SystemController::class, 'healthCheck' ])
         ->get ('/heroes',       [ HeroesController::class, 'index' ])
         ->post ('/heroes',      [ HeroesController::class, 'store' ])
         ->get ('/heroes/{id}',  [ HeroesController::class, 'show' ])

         ->group ('/gateway', fn ($group) =>
            $group
               ->post ('/tokens/login',   [ TokenController::class, 'login' ])
               ->post ('/tokens/refresh', [ TokenController::class, 'refresh' ])
               ->post ('/tokens/logout',  [ TokenController::class, 'logout' ])
                  ->middleware (TokenAuthentication::class)

               ->post ('/cookie/login',  [ CookieController::class, 'login' ])
               ->post ('/cookie/logout', [ CookieController::class, 'logout' ])
         )

         // Supports both token and cookie auth
         // CookieAuthentication runs globally, TokenAuthentication provides fallback for Bearer tokens
         ->get ('/users/@me', [ UserController::class, 'me' ])
            ->middleware (TokenAuthentication::class)
   );

$app->run ();