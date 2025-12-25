<?php

namespace App\Controller;

use Symfony\Component\Routing\Attribute\Route;
use Wisp\Attribute\After;
use Wisp\Attribute\Before;
use Wisp\Attribute\Bearer;
use Wisp\Attribute\Throttle;
use Wisp\Http\Request;
use Wisp\Http\Response;

/**
 * Controller for testing new Wisp features.
 */
#[Route ('/v1/test')]
class TestFeaturesController
{
   private bool $maintenanceMode = false;

   // === Before/After Hooks ===

   #[Before]
   public function logRequest (Request $request): ?Response
   {
      // Add a header to prove the hook ran
      $request->attributes->set ('hook:before', true);
      return null;
   }

   #[Before (only: [ 'maintenance' ])]
   public function checkMaintenance (Request $request): ?Response
   {
      if ($request->headers->get ('X-Force-Maintenance') === 'true') {
         return (new Response)
            ->status (503)
            ->json ([ 'message' => 'Service under maintenance' ]);
      }

      return null;
   }

   #[After]
   public function addResponseHeader (Request $request, Response $response): Response
   {
      $response->headers->set ('X-Hook-After', 'executed');
      return $response;
   }

   #[After (except: [ 'noAfterHook' ])]
   public function addTimestamp (Request $request, Response $response): Response
   {
      $response->headers->set ('X-Timestamp', (string) time ());
      return $response;
   }

   // === Endpoints ===

   #[Route ('/hooks', methods: [ 'GET' ])]
   public function hooks (Request $request): Response
   {
      return (new Response)->json ([
         'before_hook_ran' => $request->attributes->get ('hook:before', false)
      ]);
   }

   #[Route ('/maintenance', methods: [ 'GET' ])]
   public function maintenance (Request $request): Response
   {
      return (new Response)->json ([ 'status' => 'operational' ]);
   }

   #[Route ('/no-after-hook', methods: [ 'GET' ])]
   public function noAfterHook (Request $request): Response
   {
      return (new Response)->json ([ 'ok' => true ]);
   }

   // === Tiered Throttling ===

   #[Route ('/throttle/default', methods: [ 'GET' ])]
   #[Throttle (limit: 5, interval: 60)]
   public function throttleDefault (): Response
   {
      return (new Response)->json ([ 'tier' => 'default' ]);
   }

   #[Route ('/throttle/tiered', methods: [ 'GET' ])]
   #[Throttle (limit: 2, interval: 60)]
   #[Throttle (limit: 100, interval: 60, for: 'ROLE_PREMIUM')]
   #[Throttle (limit: 0, interval: 60, for: 'ROLE_ADMIN')]
   public function throttleTiered (): Response
   {
      return (new Response)->json ([ 'tier' => 'tiered' ]);
   }

   // === Accepted Response ===

   #[Route ('/async-job', methods: [ 'POST' ])]
   public function asyncJob (Request $request): Response
   {
      $jobId = 'job-' . bin2hex (random_bytes (8));

      return (new Response)->accepted ($jobId);
   }

   // === Bearer Token ===

   #[Route ('/bearer/required', methods: [ 'GET' ])]
   #[Bearer]
   public function bearerRequired (Request $request): Response
   {
      return (new Response)->json ([
         'authenticated' => true,
         'claims' => $request->bearer ()
      ]);
   }

   #[Route ('/bearer/claims', methods: [ 'GET' ])]
   #[Bearer (claims: [ 'scope' => 'admin' ])]
   public function bearerWithClaims (Request $request): Response
   {
      return (new Response)->json ([
         'scope' => $request->bearer ('scope')
      ]);
   }

   #[Route ('/bearer/optional', methods: [ 'GET' ])]
   public function bearerOptional (Request $request): Response
   {
      return (new Response)->json ([
         'has_bearer' => $request->bearerValid (),
         'sub' => $request->bearer ('sub', 'anonymous')
      ]);
   }
}
