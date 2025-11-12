<?php

require __DIR__ . '/../vendor/autoload.php';

use Example\Controller\AuthController;
use Example\Controller\OAuthController;
use Example\Controller\ProfileController;
use Example\Security\OAuthUserMapper;
use Wisp\Environment\Stage;
use Wisp\Http\Response;
use Wisp\Middleware\Authentication\ApiKeyAuthentication;
use Wisp\Middleware\Authentication\CookieAuthentication;
use Wisp\Middleware\Authentication\OAuthAuthentication;
use Wisp\Middleware\Authentication\TokenAuthentication;
use Wisp\Middleware\CORS;
use Wisp\Middleware\CSRF;
use Wisp\Middleware\Helmet;
use Wisp\Middleware\Session;
use Wisp\Security\Contracts\OAuthUserMapperInterface;
use Wisp\Security\OAuth\OAuthManager;
use Wisp\Security\User;
use Wisp\Wisp;

$app = new Wisp ([
   'name'    => 'Wisp',
   'root'    => __DIR__,
   'stage'   => Stage::development,
   'debug'   => true,
   'version' => '1.0.0'
]);

Wisp::container ()
   ->register (OAuthManager::class)
   ->setPublic (true)
   ->setArgument ('$providerConfigs', [
      'github' => [
         'client_id'     => $_ENV ['GITHUB_CLIENT_ID'] ?? 'your_github_client_id',
         'client_secret' => $_ENV ['GITHUB_CLIENT_SECRET'] ?? 'your_github_client_secret',
         'redirect_uri'  => 'http://localhost:8000/auth/oauth/github/callback'
      ],
      'google' => [
         'client_id'     => $_ENV ['GOOGLE_CLIENT_ID'] ?? 'your_google_client_id',
         'client_secret' => $_ENV ['GOOGLE_CLIENT_SECRET'] ?? 'your_google_client_secret',
         'redirect_uri'  => 'http://localhost:8000/auth/oauth/google/callback'
      ]
   ]);

Wisp::container ()
   ->register (OAuthUserMapperInterface::class)
   ->setClass (OAuthUserMapper::class)
   ->setPublic (true);

$app
   ->middleware (Helmet::class)
   ->middleware (CORS::class)
   ->middleware (CSRF::class)
   ->middleware (Session::class);

$app->group ('/v1', fn ($group) =>
   $group
      ->middleware (TokenAuthentication::class, [
         'user_provider' => fn ($id) => new User (
            id: $id,
            role: 'user',
            permissions: [ 
               'read:own', 
               'write:own' 
            ]
         )
      ])

      ->group ('/gateway', fn ($group) =>
         $group
            ->post ('/login',   [ AuthController::class, 'loginWithToken' ])
            ->post ('/refresh', [ AuthController::class, 'refreshToken'   ])
            ->post ('/logout',  [ AuthController::class, 'logout'         ])
      )

      ->get ('/health', fn () => ['status' => 'ok'])
      ->get ('/profile', [ ProfileController::class, 'show' ])
         ->is ('user')

      ->get ('/admin/users', [ ProfileController::class, 'listUsers' ])
         ->is ('admin')
         ->can ('read:users')
);

// // Token auth endpoints (login, refresh, logout)
// $app->group ('/auth/token', fn ($group) =>
//    $group
//       ->post ('/login', [AuthController::class, 'tokenLogin'])
//       ->post ('/refresh', [AuthController::class, 'tokenRefresh'])
//       ->post ('/logout', [AuthController::class, 'tokenLogout'])
// );

// //==============================================================================
// // COOKIE AUTHENTICATION (Web Routes)
// //==============================================================================

// $app->group ('/admin', fn ($group) =>
//    $group
//       // Apply cookie authentication + CSRF to this group
//       ->middleware (CookieAuthentication::class, [
//          'user_provider' => fn ($id) => new User (
//             id: $id,
//             role: 'admin',
//             permissions: ['*']
//          ),
//          'remember_ttl' => 2592000  // 30 days
//       ])
//       ->middleware (CSRF::class)

//       // All routes require admin role
//       ->is ('admin')

//       ->get ('/dashboard', fn () => ['message' => 'Admin Dashboard'])
//       ->get ('/users', fn () => ['users' => []])
//          ->can ('read:users')
//       ->post ('/users', fn () => ['message' => 'User created'])
//          ->can ('create:users')
// );

// // Cookie auth endpoints
// $app->group ('/auth/web', fn ($group) =>
//    $group
//       ->middleware (CSRF::class)
//       ->post ('/login', [AuthController::class, 'webLogin'])
//       ->post ('/logout', [AuthController::class, 'webLogout'])
// );

// //==============================================================================
// // API KEY AUTHENTICATION (Service Routes)
// //==============================================================================

// $app->group ('/services', fn ($group) =>
//    $group
//       // Apply API key authentication
//       ->middleware (ApiKeyAuthentication::class, [
//          'validator' => fn ($key) => match ($key) {
//             'dev_key_12345' => new User (1, 'service', ['read:data', 'write:data']),
//             'admin_key_67890' => new User (2, 'service', ['*']),
//             default => null
//          }
//       ])

//       ->get ('/data', fn () => ['data' => 'Service data'])
//          ->can ('read:data')

//       ->post ('/data', fn () => ['message' => 'Data created'])
//          ->can ('write:data')
// );

// //==============================================================================
// // OAUTH AUTHENTICATION (Social Login)
// //==============================================================================

// // OAuth redirect and callback endpoints (public, no auth)
// $app->group ('/auth/oauth', fn ($group) =>
//    $group
//       // Redirect to OAuth provider (GitHub, Google, etc.)
//       ->get ('/{provider}', [OAuthController::class, 'redirect'])

//       // OAuth callback handler
//       ->get ('/{provider}/callback', [OAuthController::class, 'callback'])

//       // Logout from OAuth session
//       ->post ('/logout', [OAuthController::class, 'logout'])
// );

// // Protected routes that accept OAuth authentication
// $app->group ('/dashboard', fn ($group) =>
//    $group
//       // Apply OAuth authentication middleware
//       ->middleware (OAuthAuthentication::class, [
//          'user_provider' => fn ($id) => new User (
//             id: $id,
//             role: 'user',
//             permissions: ['read:own', 'write:own']
//          )
//       ])

//       // Require user to be authenticated
//       ->is ('user')

//       ->get ('/', fn () => ['message' => 'Welcome to your dashboard'])
//       ->get ('/settings', fn () => ['message' => 'User settings'])
//          ->can ('read:own')
// );

// //==============================================================================
// // MULTIPLE AUTHENTICATORS (Flexible Routes)
// //==============================================================================

// $app->group ('/hybrid', fn ($group) =>
//    $group
//       // Try token, then cookie, then API key
//       ->middleware (TokenAuthentication::class)
//       ->middleware (CookieAuthentication::class)
//       ->middleware (ApiKeyAuthentication::class, [
//          'validator' => fn ($key) => $key === 'test_key'
//             ? new User (1, 'user', ['read:own'])
//             : null
//       ])

//       ->get ('/profile', fn () => ['message' => 'Accessible with any auth method'])
//          ->is ('user')
// );


$app->run ();
