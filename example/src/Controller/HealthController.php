<?php

namespace App\Controller;

use Symfony\Component\Routing\Attribute\Route;
use Wisp\Attribute\Log;
use Wisp\Http\Response;

#[Log]
class HealthController
{
   #[Route ('/v1/health', methods: [ 'GET' ])]
   public function __invoke (): Response
   {
      return (new Response)->json ([ 'status' => 'ok' ]);
   }
}
