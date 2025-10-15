Routing:

   ->[get|post|...] ('/path/{name}', [ Controller::class, 'action' ])
   ->[get|post|...] ('/path/{name}', function (Request $request, string $name) {})

Middleware:

   ->before (function (Request $request) {})
   ->after (function (Request $request) {})

   ->middleware (Middleware::class, [ 'settings' => 'abc' ])

->on (404, [ Controller::class, 'action' ])
->on (404, function (Request $request) {})