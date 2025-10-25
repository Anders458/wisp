<?php

require 'vendor/autoload.php';

use Psr\Log\LoggerInterface;
use Wisp\Environment\Stage;
use Wisp\Example\Controller\Error;
use Wisp\Example\Controller\Heroes;
use Wisp\Example\Controller\Matches;
use Wisp\Example\Controller\Players;
use Wisp\Example\Controller\System;
use Wisp\Example\Middleware\Auth;
use Wisp\Example\Middleware\Envelope;
use Wisp\Example\Middleware\RateLimit;
use Wisp\Example\Middleware\Timer;
use Wisp\Http\Request;
use Wisp\Wisp;

$app = new Wisp (
   [
      'name'    => 'Wisp API Example',
      'root'    => __DIR__,
      'debug'   => true,
      'stage'   => Stage::development,
      'version' => '1.0.0',
   ]
);

$app
   ->on (404, [ Error::class, 'notFound' ])
   ->on (500, [ Error::class, 'internalError' ])

   ->middleware (Envelope::class)

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
         ->middleware (Timer::class, [ 'label' => 'API v1 Group' ])
         ->middleware (RateLimit::class, [ 'maxRequests' => 1000 ])
         ->middleware (Auth::class)

         ->get ('/health-check', [ System::class, 'healthCheck' ])

         ->get ('/heroes', [ Heroes::class, 'index' ])
            ->middleware (Timer::class, [ 'label' => 'Heroes Index' ])

         ->get ('/heroes/{id}', [ Heroes::class, 'show' ])

         ->get ('/heroes/{id}/stats', [ Heroes::class, 'stats' ])
         ->get ('/matches', [ Matches::class, 'index' ])

         ->get ('/matches/recent', [ Matches::class, 'recent' ])
            ->middleware (Timer::class, [ 'label' => 'Recent Matches' ])

         ->get ('/matches/{id}', [ Matches::class, 'show' ])
         ->get ('/players', [ Players::class, 'index' ])

         ->get ('/players/rankings', [ Players::class, 'rankings' ])
            ->middleware (Timer::class, [ 'label' => 'Player Rankings' ])

         ->get ('/players/{username}', [ Players::class, 'show' ])
         ->get ('/players/{username}/matches', [ Players::class, 'matches' ])
   );

$app->run ();
