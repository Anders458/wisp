<?php

require 'vendor/autoload.php';

use Psr\Log\LoggerInterface;
use Wisp\Environment\Stage;
use Wisp\Example\Controller\Error;
use Wisp\Example\Controller\Examples;
use Wisp\Example\Controller\Heroes;
use Wisp\Example\Controller\System;
use Wisp\Example\Middleware\Auth;
use Wisp\Example\Middleware\RateLimit;
use Wisp\Example\Middleware\Timer;
use Wisp\Http\Request;
use Wisp\Middleware\Authentication;
use Wisp\Middleware\Cache;
use Wisp\Middleware\Compression;
use Wisp\Middleware\CSRF;
use Wisp\Middleware\Envelope;
use Wisp\Middleware\Helmet;
use Wisp\Middleware\Session;
use Wisp\Middleware\Throttle;
use Wisp\Service\Keychain;
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
   ->on (404, [ Error::class, 'notFound' ])
   ->on (500, [ Error::class, 'internalError' ])

   ->middleware (Session::class)
   ->middleware (CSRF::class)
   ->middleware (Helmet::class)
   ->middleware (Envelope::class)

   ->before (function () {
      $config   = container (Keychain::class)->get ('config');
      $settings = container (Keychain::class)->get ('settings');
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
         ->get ('/redirect', [ Examples::class, 'redirect' ])
         ->get ('/download', [ Examples::class, 'download' ])
         ->get ('/html', [ Examples::class, 'html' ])
         ->get ('/text', [ Examples::class, 'text' ])
   )

   ->group ('/v1', fn ($group) =>
      $group
         ->middleware (Throttle::class, [ 
            'limit' => 100, 
            'window' => 60 
         ])
         ->middleware (Authentication::class)

         ->get ('/health-check', [ System::class, 'healthCheck' ])
         ->get ('/heroes', [ Heroes::class, 'index' ])
         ->post ('/heroes', [ Heroes::class, 'store' ])
         ->get ('/heroes/{id}', [ Heroes::class, 'show' ])
         ->get ('/heroes/{id}/stats', [ Heroes::class, 'stats' ])
   );

$app->run ();