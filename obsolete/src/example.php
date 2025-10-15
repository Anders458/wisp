<?php

if (is_file ('vendor/autoload.php')) {
   require_once ('vendor/autoload.php');
} else if (is_file ('../../autoload.php')) {
   require_once ('../../autoload.php');
}

use Wisp\Controller;
use Wisp\Environment\Stage;
use Wisp\Http\Request;
use Wisp\Http\Response;
use Wisp\Middleware\Envelope;
use Wisp\Middleware\Session;
use Wisp\Middleware\Throttle;
use Wisp\Util\Logger;
use Wisp\Wisp;

// -- -- //

class System extends Controller
{
   public function guarded ()
   {
   }

   public function healthCheck ()
   {
      $this->response->body = gmdate ('Y-m-d H:i:s');
   }

   public function notFound (Request $request, Response $response)
   {
      die ('404');
   }

   public function internalError (Request $request, Response $response)
   {
      die ('500');
   }
}

// -- -- //

/*

The setup parameters should be dynamically configured based on your own
application logic.

*/
define ('DEBUG'  , TRUE);
define ('VERSION', '1.0.0');
define ('STAGE'  , Stage::development);

Wisp::setup (
   [
      'debug'   => DEBUG,
      'version' => VERSION,
      'stage'   => STAGE
   ]
);

/*
   Lifecycle:
      Before -> Middleware (before) -> Handle -> Status Listeners -> Middleware (after) -> After
*/

$url = Request::getUrl ();
$url->path = $url->query ['path'];

$request = new Request (
   'GET',
   $url
);

Wisp::router ()
   // ->secure (true)
   
   ->on (404, [ System::class, 'notFound' ])
   ->on (500, [ System::class, 'internalError' ])

   ->before (fn (Request $request, Logger $logger) =>
      $logger->info ('({ip}) {method} {url}', [
         'ip'     => $request->ip (),
         'method' => $request->method,
         'url'    => $request->url->toString ()
      ])
   )

   ->middleware (new Session ())
   ->middleware (new Envelope ())
   
   // Requires APCu extension
   ->middleware (new Throttle ([
      'requests' => 10,
      'period' => 30
   ]))

   // ->guard (new Throttle ())
   
   ->group ('/v1', fn ($group) =>
      $group
         ->get ('/health-check', [ System::class, 'healthCheck' ])
         ->get ('/guarded', [ System::class, 'guarded' ])
         
         // Requires Session middleware (or a middleware to populate $request->session)
         ->guard ()
            ->is ([ 'admin' ])
            ->can ([ 'view', 'edit' ])
   )

   // ->before (function (Request $request, Response $response) {
   //    $response->headers ['Content-Type'] = 'application/json';

   //    $request->session = new stdClass ();
   //    $request->session->user = 'Anders';
   // })

   // ->after (function () {
   //    // Cleanup request, close database connections etc.
   // })

   // ->middleware (new Envelope ())
   // ->middleware (new Session ())

   // ->middleware (new CORS ([
   //    'origins' => [ 'example.com', 'api.example.com', 'localhost:8888' ],
   //    'methods' => [ 'GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS' ],
   //    'headers' => [ 'Content-Type', 'Authorization', 'X-Requested-With' ],
      
   //    'exposed_headers' => [ 'X-Token' ],
   //    'max_age' => 3600,
   //    'credentials' => true,
      
   //    'private_network' => true,
   // ]))

   // ->domain ('example.com', function ($domain) {
   //    $domain
   //       ->protocol ('http')
   //       ->auth ('guest', 'password')
   //       ->port (8888)
   //       ->secure (true)
   //       ->path ('/v1');
   // })

   // ->group ('/v1', function ($group) {
   //    if (@!!$_ENV ['DEBUG']) {
   //       $group->middleware (new Throttle (100));
   //    }

   //    if (@!!$_ENV ['MAINTENANCE']) {
   //       $group->redirect ('*', '/maintenance', 503);
   //    }
      
   //    $group
   //       ->path ('/v2')
   //       ->any ('/health', function (Request $request, Response $response) {
   //          $response
   //             ->status (200)
   //             ->body ([
   //                'timestamp' => time (),
   //                'user' => $request->session->user
   //             ]);
   //       })
   //          ->alias ('/health-check')
   //          ->path ('/system-status')

   //       ->group ('/users', function ($group) {
   //          $group
   //             ->group ('', function ($group) {
   //                $group
   //                   ->get ('/{id}', [ User::class, 'get' ])
   //                   ->delete ('/{id}', [ User::class, 'delete' ])
   //                   ->match ('/{id}', [ User::class, 'update' ], [ 'PUT', 'PATCH' ]);
   //             })

   //             ->group ('/@me', function ($group) { 
   //                $group
   //                   ->get ('/', [ User::class, 'me' ])
   //                      ->priority (10)
                     
   //                   ->get ('/checkout', [ User::class, 'checkout' ])
   //                   ->get ('/checkout/cancel', [ User::class, 'cancel' ]);
   //             });
   //       })

   //       /*
   //          Optional params:
   //             /blog/2024/09/21
   //                $request->get ('year') -> 2024
   //                $request->get ('month') -> 09
   //                $request->get ('day') -> 21
   //             /blog/2024/10 
   //                $request->get ('year') -> 2024
   //                $request->get ('month') -> 09
   //                $request->get ('day') -> null
   //       */
   //       ->get ('/blog[/{year:[0-9]{4}}[/{month:[0-9]{2}}[/{day:[0-9]{2}}]]]', function (Request $request, Response $response) {

   //       })

   //       /*
   //          Repeated params (/blog/chapter/page/section-1)
   //             $request->get ('slug') -> [ 'chapter', 'page', 'section-1' ]
   //       */      
   //       ->get ('/blog(/{slug:[A-Za-z0-9\-]+}){1,3}(/{tail}){2}', function (Request $request, Response $response) {

   //       })
   //          ->priority (50)
   //          ->name ('post');
   // })

   ->dispatch ($request);