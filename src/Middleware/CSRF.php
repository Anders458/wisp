<?php

/**
 * CSRF Protection Middleware
 *
 * Protects against Cross-Site Request Forgery attacks by validating tokens
 * on state-changing HTTP requests (POST, PUT, DELETE, PATCH).
 *
 * USAGE:
 *
 * 1. Register middleware globally:
 *
 *    $app->middleware (Session::class);
 *    $app->middleware (CSRF::class);
 *
 * 2. Register with exclusions for API endpoints:
 *
 *    $app->middleware (CSRF::class, [ 'exclude' => [ '/api/*', '/webhooks/*' ] ]);
 *
 * 3. Register at group level:
 *
 *    $app->group ('/admin', fn ($group) =>
 *       $group
 *          ->middleware (CSRF::class)
 *          ->get ('/dashboard', [ AdminController::class, 'dashboard' ])
 *          ->post ('/settings', [ AdminController::class, 'updateSettings' ])
 *    );
 *
 * 4. Register at route level:
 *
 *    $app->post ('/payment', [ PaymentController::class, 'process' ])
 *       ->middleware (CSRF::class);
 *
 * 5. For AJAX/SPA applications using Envelope middleware:
 *
 *    The CSRF token is automatically included in every JSON response at the
 *    top level of the envelope. No manual token retrieval needed!
 *
 *    // Any JSON response will include:
 *    {
 *       "csrf": "abc123...",
 *       "version": "1.0.0",
 *       "status": "OK",
 *       "body": { ... }
 *    }
 *
 *    // Your frontend can extract and use it:
 *    const response = await fetch ('/api/data').then (r => r.json ());
 *    const csrfToken = response.csrf;
 *
 *    fetch ('/api/submit', {
 *       method: 'POST',
 *       headers: {
 *          'X-CSRF-Token': csrfToken,
 *          'Content-Type': 'application/json'
 *       },
 *       body: JSON.stringify (data)
 *    });
 *
 * 6. Custom header name (optional):
 *
 *    $app->middleware (CSRF::class, [ 'headerName' => 'X-XSRF-Token' ]);
 *
 * TOKEN ROTATION:
 *
 * Tokens automatically rotate after every successful validation, providing
 * single-use token security. This maximizes protection against CSRF attacks
 * by minimizing the window of opportunity for token theft.
 *
 * Manual rotation is rarely needed, but available for edge cases:
 *
 *    $csrf = container (CSRF::class);
 *    $csrf->rotateToken ();  // Force new token (e.g., on logout)
 *
 * EXCLUSIONS:
 *
 * Why exclude certain routes?
 *
 * - API endpoints with token-based auth (OAuth, JWT): These already have
 *   authentication mechanisms. Adding CSRF tokens creates friction for
 *   API consumers who need stateless requests.
 *
 * - Webhooks from third-party services: External services like Stripe,
 *   GitHub, etc. can't provide your CSRF tokens. Use signature verification
 *   or secret keys instead.
 *
 * - Public read-only endpoints: GET requests aren't validated by default,
 *   but if you have public POST endpoints that don't modify state
 *   (like search), you can exclude them.
 *
 * - Server-to-server communication: Internal microservices or trusted
 *   backend services shouldn't need CSRF tokens.
 *
 * Example exclusion patterns:
 *   '/api/*'              - All API routes
 *   '/webhooks/stripe'    - Specific webhook endpoint
 *   '/public/search'      - Public search endpoint
 *   '/health'             - Health check endpoint
 */

namespace Wisp\Middleware;

use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Wisp\Http\Request;
use Wisp\Http\Response;

class CSRF
{
   public function __construct (
      private SessionInterface $session,
      private Request $request,
      private Response $response,
      private array $exclude = [],
      private string $headerName = 'X-CSRF-Token'
   )
   {
   }

   public function before ()
   {
      if (!$this->session->has ('csrf_token')) {
         $this->rotateToken ();
      }

      // Validate token for state-changing requests
      if (in_array ($this->request->getMethod (), [ 'POST', 'PUT', 'DELETE', 'PATCH' ])) {
         if ($this->isExcluded ()) {
            return;
         }

         $token = $this->request->headers->get ($this->headerName);

         if (!hash_equals ($this->session->get ('csrf_token'), $token ?? '')) {
            return $this->response
               ->status (403)
               ->error ('Invalid CSRF token');
         }

         $this->rotateToken ();
      }
   }

   public function getToken () : string
   {
      if (!$this->session->has ('csrf_token')) {
         $this->rotateToken ();
      }

      return $this->session->get ('csrf_token');
   }

   public function rotateToken () : void
   {
      $this->session->set ('csrf_token', bin2hex (random_bytes (32)));
   }

   private function isExcluded () : bool
   {
      $path = $this->request->getPathInfo ();

      foreach ($this->exclude as $pattern) {
         if (fnmatch ($pattern, $path)) {
            return true;
         }
      }

      return false;
   }
}
