<?php

namespace Example\Controller;

use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Wisp\Http\Request;
use Wisp\Http\Response;
use Wisp\Security\Contracts\OAuthUserMapperInterface;
use Wisp\Security\OAuth\OAuthManager;

class OAuthController
{
   public function __construct (
      private Request $request,
      private Response $response,
      private SessionInterface $session,
      private OAuthManager $oauthManager,
      private OAuthUserMapperInterface $userMapper
   ) {}

   /**
    * Redirect to OAuth provider
    * GET /auth/oauth/{provider}
    *
    * Example: GET /auth/oauth/github
    */
   public function redirect (string $provider) : Response
   {
      try {
         $oauthProvider = $this->oauthManager->getProvider ($provider);

         // Generate authorization URL
         $authUrl = $oauthProvider->getAuthorizationUrl ();

         // Store state in session for CSRF protection
         $this->session->set ('oauth_state', $oauthProvider->getState ());
         $this->session->set ('oauth_provider', $provider);

         return $this->response->redirect ($authUrl);
      } catch (\RuntimeException $e) {
         return $this->response
            ->status (400)
            ->json (['error' => $e->getMessage ()]);
      }
   }

   /**
    * Handle OAuth callback
    * GET /auth/oauth/{provider}/callback
    *
    * Example: GET /auth/oauth/github/callback?code=...&state=...
    */
   public function callback (string $provider) : Response
   {
      try {
         // SECURITY: Verify state parameter to prevent CSRF attacks
         $state = $this->request->query->get ('state');
         $sessionState = $this->session->get ('oauth_state');

         // Use constant-time comparison to prevent timing attacks
         if (empty ($state) || empty ($sessionState) || !hash_equals ($sessionState, $state)) {
            return $this->response
               ->status (400)
               ->json (['error' => 'Invalid state parameter (CSRF protection)']);
         }

         // Get authorization code
         $code = $this->request->query->get ('code');

         if (empty ($code)) {
            return $this->response
               ->status (400)
               ->json (['error' => 'No authorization code provided']);
         }

         // Get OAuth provider
         $oauthProvider = $this->oauthManager->getProvider ($provider);

         // Exchange code for access token
         $accessToken = $oauthProvider->getAccessToken ('authorization_code', [
            'code' => $code
         ]);

         // Get user data from provider
         $oauthUser = $oauthProvider->getResourceOwner ($accessToken);

         // Map OAuth user to application User
         $user = $this->userMapper->map ($oauthUser, $provider);

         if (!$user) {
            return $this->response
               ->status (403)
               ->json (['error' => 'User mapping failed']);
         }

         // Store user ID in session (same as cookie auth)
         $this->session->set ('user_id', $user->getUserIdentifier ());

         // Clear OAuth state
         $this->session->remove ('oauth_state');
         $this->session->remove ('oauth_provider');

         // Redirect to dashboard or return success
         return $this->response->json ([
            'message' => 'Successfully authenticated',
            'user' => [
               'id' => $user->getUserIdentifier (),
               'role' => $user->getRole (),
               'permissions' => $user->getPermissions ()
            ]
         ]);

      } catch (\Exception $e) {
         return $this->response
            ->status (500)
            ->json (['error' => 'OAuth authentication failed: ' . $e->getMessage ()]);
      }
   }

   /**
    * Logout from OAuth session
    * POST /auth/oauth/logout
    */
   public function logout () : Response
   {
      $this->session->invalidate ();

      return $this->response->json ([
         'message' => 'Successfully logged out'
      ]);
   }
}
