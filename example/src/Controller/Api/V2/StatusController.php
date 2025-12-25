<?php

namespace App\Controller\Api\V2;

use Symfony\Component\Routing\Attribute\Route;
use Wisp\Http\Response;

#[Route ('/status')]
class StatusController extends BaseController
{
   #[Route ('', methods: [ 'GET' ])]
   public function index (): Response
   {
      return (new Response)->json ([
         'version' => 'v2',
         'status' => 'ok'
      ]);
   }

   #[Route ('/health', methods: [ 'GET' ])]
   public function health (): Response
   {
      return (new Response)->json ([
         'healthy' => true
      ]);
   }
}
