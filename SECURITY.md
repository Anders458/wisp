# Wisp Security

Wisp provides enterprise-grade security powered by **Symfony Security** components with an ergonomic, minimalist API.

## Features

- ✅ **Multiple Authentication Methods**: Token, Cookie, API Key, OAuth
- ✅ **Authorization Voters**: Symfony's robust voter system
- ✅ **CSRF Protection**: Symfony's proven CSRF implementation
- ✅ **Flexible User Storage**: Cache, database, or custom providers
- ✅ **Closure Support**: Quick prototyping with inline logic
- ✅ **Class-Based Providers**: Reusable, testable implementations

---

## Quick Start

### Token Authentication (API)

```php
use Wisp\Middleware\Authentication\TokenAuthentication;
use Wisp\Middleware\Guard;

$app->group ('/api', fn ($group) =>
   $group
      ->middleware (TokenAuthentication::class, [
         'user_provider' => fn ($id) => User::find ($id)
      ])
      ->middleware (Guard::class)
      ->get ('/profile', [ProfileController::class, 'show'])
         ->is ('user')
);
```

**Login:**
```bash
curl -X POST /auth/login -d '{"email":"user@example.com","password":"secret"}'
# Returns: {"access_token":"...","refresh_token":"...","expires_in":3600}
```

**Access Protected Route:**
```bash
curl -X GET /api/profile -H "Authorization: Bearer <access_token>"
```

---

## Authentication Methods

### 1. Token Authentication (Stateless)

For SPAs, mobile apps, and APIs.

```php
use Wisp\Middleware\Authentication\TokenAuthentication;

$app->middleware (TokenAuthentication::class, [
   'user_provider' => fn ($id) => User::find ($id),
   'ttl' => [
      'access'  => 7200,   // 2 hours
      'refresh' => 604800  // 7 days
   ]
]);
```

**Controller Usage:**

```php
class AuthController
{
   public function __construct (private AccessTokenProvider $accessTokenProvider) {}

   public function login (Request $request) : Response
   {
      // Validate credentials
      $user = $this->validateCredentials ($request);

      // Generate tokens
      $tokens = $this->accessTokenProvider->become (
         userId: $user->id,
         role: $user->role,
         permissions: $user->permissions
      );

      return $this->response->json ($tokens);
   }

   public function refresh (Request $request) : Response
   {
      $refreshToken = $request->input ('refresh_token');
      $tokens = $this->accessTokenProvider->refresh ($refreshToken);

      return $this->response->json ($tokens);
   }

   public function logout (Request $request) : Response
   {
      $refreshToken = $request->input ('refresh_token');
      $this->tokenManager->revoke ($refreshToken);

      return $this->response->json (['message' => 'Logged out']);
   }
}
```

---

### 2. Cookie Authentication (Stateful)

For traditional web applications.

```php
use Wisp\Middleware\Authentication\CookieAuthentication;

$app->middleware (CookieAuthentication::class, [
   'user_provider' => fn ($id) => User::find ($id),
   'remember_ttl' => 2592000  // 30 days
]);
```

**Controller Usage:**

```php
class AuthController
{
   public function __construct (private SessionInterface $session) {}

   public function login (Request $request) : Response
   {
      $user = $this->validateCredentials ($request);

      // Set user in session
      $this->session->set ('user_id', $user->id);

      return $this->response->redirect ('/dashboard');
   }

   public function logout () : Response
   {
      $this->session->invalidate ();
      return $this->response->redirect ('/');
   }
}
```

---

### 3. API Key Authentication

For service-to-service communication.

```php
use Wisp\Middleware\Authentication\ApiKeyAuthentication;

// Inline validator (simple)
$app->middleware (ApiKeyAuthentication::class, [
   'validator' => fn ($key) => match ($key) {
      'service_key_123' => new User (1, 'service', ['read:data']),
      default => null
   }
]);

// Or class-based validator (reusable)
$app->middleware (ApiKeyAuthentication::class, [
   'validator' => DatabaseApiKeyValidator::class
]);
```

**Client Usage:**

```bash
curl -X GET /api/data -H "X-API-Key: service_key_123"
```

---

### 4. OAuth Authentication

For social login (GitHub, Google, etc.).

**Step 1: Register OAuth Manager**

```php
use Wisp\Security\OAuth\OAuthManager;
use Wisp\Security\Contracts\OAuthUserMapperInterface;

// Register OAuth manager with providers
Wisp::container ()
   ->register (OAuthManager::class)
   ->setPublic (true)
   ->setArgument ('$providerConfigs', [
      'github' => [
         'client_id'     => $_ENV ['GITHUB_CLIENT_ID'],
         'client_secret' => $_ENV ['GITHUB_CLIENT_SECRET'],
         'redirect_uri'  => 'https://yourapp.com/auth/oauth/github/callback'
      ],
      'google' => [
         'client_id'     => $_ENV ['GOOGLE_CLIENT_ID'],
         'client_secret' => $_ENV ['GOOGLE_CLIENT_SECRET'],
         'redirect_uri'  => 'https://yourapp.com/auth/oauth/google/callback'
      ]
   ]);

// Register OAuth user mapper
Wisp::container ()
   ->register (OAuthUserMapperInterface::class)
   ->setClass (OAuthUserMapper::class)
   ->setPublic (true);
```

**Step 2: Create OAuth User Mapper**

```php
use Wisp\Security\Contracts\OAuthUserMapperInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Wisp\Security\User;

class OAuthUserMapper implements OAuthUserMapperInterface
{
   public function map (object $oauthUser, string $provider) : ?UserInterface
   {
      // Find or create user in your database
      $user = User::findByOAuthProvider ($provider, $oauthUser->getId ());

      if (!$user) {
         $user = User::create ([
            'oauth_provider' => $provider,
            'oauth_id' => $oauthUser->getId (),
            'email' => $oauthUser->getEmail (),
            'name' => $oauthUser->getName ()
         ]);
      }

      return new User (
         id: $user->id,
         role: $user->role,
         permissions: $user->permissions
      );
   }
}
```

**Step 3: Create OAuth Controller**

```php
use Wisp\Security\OAuth\OAuthManager;
use Wisp\Security\Contracts\OAuthUserMapperInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class OAuthController
{
   public function __construct (
      private Request $request,
      private Response $response,
      private SessionInterface $session,
      private OAuthManager $oauthManager,
      private OAuthUserMapperInterface $userMapper
   ) {}

   // Redirect to OAuth provider
   public function redirect (string $provider) : Response
   {
      $oauthProvider = $this->oauthManager->getProvider ($provider);
      $authUrl = $oauthProvider->getAuthorizationUrl ();

      $this->session->set ('oauth_state', $oauthProvider->getState ());

      return $this->response->redirect ($authUrl);
   }

   // Handle OAuth callback
   public function callback (string $provider) : Response
   {
      $code = $this->request->query->get ('code');
      $oauthProvider = $this->oauthManager->getProvider ($provider);

      $accessToken = $oauthProvider->getAccessToken ('authorization_code', ['code' => $code]);
      $oauthUser = $oauthProvider->getResourceOwner ($accessToken);

      // Map OAuth user to your User model
      $user = $this->userMapper->map ($oauthUser, $provider);

      // Store user ID in session
      $this->session->set ('user_id', $user->getUserIdentifier ());

      return $this->response->redirect ('/dashboard');
   }
}
```

**Step 4: Setup Routes**

```php
use Wisp\Middleware\Authentication\OAuthAuthentication;

// OAuth flow endpoints (public)
$app->group ('/auth/oauth', fn ($group) =>
   $group
      ->get ('/{provider}', [OAuthController::class, 'redirect'])
      ->get ('/{provider}/callback', [OAuthController::class, 'callback'])
);

// Protected routes using OAuth authentication
$app->group ('/dashboard', fn ($group) =>
   $group
      ->middleware (OAuthAuthentication::class, [
         'user_provider' => fn ($id) => User::find ($id)
      ])
      ->is ('user')
      ->get ('/', [DashboardController::class, 'index'])
);
```

**Client Usage:**

Visit `https://yourapp.com/auth/oauth/github` to initiate GitHub login.

**Supported Providers:**
- `github` - GitHub OAuth
- `google` - Google OAuth

---

## Authorization

### Guards (->is(), ->can())

```php
// Role-based (OR logic - user needs ONE role)
$app->get ('/admin', [AdminController::class, 'index'])
   ->is ('admin');

$app->get ('/dashboard', [DashboardController::class, 'index'])
   ->is (['admin', 'moderator']);

// Permission-based (AND logic - user needs ALL permissions)
$app->post ('/posts', [PostController::class, 'create'])
   ->can ('create:posts');

$app->post ('/publish', [PostController::class, 'publish'])
   ->can (['edit:posts', 'publish:posts']);

// Combined
$app->post ('/sensitive', [SensitiveController::class, 'action'])
   ->is ('admin')
   ->can ('dangerous:action');
```

### Group-Level Guards

```php
$app->group ('/admin', fn ($group) =>
   $group
      ->is ('admin')  // All routes inherit this requirement
      ->get ('/users', [AdminController::class, 'users'])
      ->delete ('/users/{id}', [AdminController::class, 'delete'])
         ->can ('delete:users')  // Additional permission
);
```

### Manual Authorization

```php
class PostController
{
   public function __construct (private Security $security) {}

   public function edit (int $id) : Response
   {
      $post = $this->findPost ($id);

      if (!$this->security->isGranted ('EDIT', $post)) {
         return $this->response->status (403)->error ('Forbidden');
      }

      // Edit post...
   }
}
```

---

## User Providers

### Closure Provider (Quick Prototyping)

```php
$app->middleware (TokenAuthentication::class, [
   'user_provider' => fn ($id) => User::find ($id)  // Eloquent
]);

$app->middleware (TokenAuthentication::class, [
   'user_provider' => fn ($id) =>
      $entityManager->find (User::class, $id)  // Doctrine
]);
```

### Class Provider (Reusable)

```php
class DatabaseUserProvider implements UserProviderInterface
{
   public function __construct (private PDO $pdo) {}

   public function loadUser (string | int $identifier) : ?UserInterface
   {
      $stmt = $this->pdo->prepare ('SELECT * FROM users WHERE id = ?');
      $stmt->execute ([$identifier]);
      $user = $stmt->fetch ();

      if (!$user) return null;

      return new User (
         id: $user ['id'],
         role: $user ['role'],
         permissions: json_decode ($user ['permissions'], true)
      );
   }
}

// Register in container
$container->set (UserProviderInterface::class, DatabaseUserProvider::class);

// Auto-wired in authenticators
$app->middleware (TokenAuthentication::class);
```

---

## CSRF Protection

```php
use Wisp\Middleware\CSRF;

$app->group ('/forms', fn ($group) =>
   $group
      ->middleware (CSRF::class)
      ->post ('/contact', [ContactController::class, 'submit'])
);
```

**Get CSRF Token:**

```php
class FormController
{
   public function __construct (private CSRF $csrf) {}

   public function show () : Response
   {
      return $this->response->html ("
         <form method='POST'>
            <input type='hidden' name='wisp:csrf.token' value='{$this->csrf->getToken()}'>
            <button>Submit</button>
         </form>
      ");
   }
}
```

**Or via Header:**

```javascript
fetch ('/forms/contact', {
   method: 'POST',
   headers: {
      'X-CSRF-Token': csrfToken,
      'Content-Type': 'application/json'
   },
   body: JSON.stringify (data)
});
```

---

## Advanced Patterns

### Multiple Authenticators (Try All)

```php
$app->group ('/api', fn ($group) =>
   $group
      ->middleware (TokenAuthentication::class)
      ->middleware (CookieAuthentication::class)
      ->middleware (ApiKeyAuthentication::class)
      ->get ('/data', [DataController::class, 'index'])
);

// Works with:
// - Authorization: Bearer <token>
// - Cookie: wisp_session=...
// - X-API-Key: <key>
```

### Firewall Pattern (Different Auth Per Group)

```php
// API - token only
$app->group ('/api', fn ($g) =>
   $g->middleware (TokenAuthentication::class)
     ->get ('/users', [UserController::class, 'index'])
);

// Admin - cookie only
$app->group ('/admin', fn ($g) =>
   $g->middleware (CookieAuthentication::class)
     ->middleware (CSRF::class)
     ->get ('/dashboard', [AdminController::class, 'index'])
);
```

---

## Migration from Old Authentication

### Before

```php
use Wisp\Middleware\Authentication;
use Wisp\Middleware\Guard;

$app->middleware (Authentication::class, ['ttl' => ['access' => 3600]]);
$app->middleware (Guard::class);
```

### After

```php
use Wisp\Middleware\Authentication\TokenAuthentication;
use Wisp\Middleware\Guard;

$app->middleware (TokenAuthentication::class, [
   'user_provider' => fn ($id) => User::find ($id),
   'ttl' => ['access' => 3600]
]);
$app->middleware (Guard::class);  // Same API!
```

**Changes:**
- `Authentication::class` → `TokenAuthentication::class`
- Add `user_provider` configuration
- Guard middleware unchanged

---

## Example Application

See `example/security.php` for a complete working example with all authentication methods.

**Run Example:**

```bash
cd example
php -S localhost:8000 security.php
```

**Test Endpoints:**

```bash
# Token auth
curl -X POST localhost:8000/auth/token/login \
  -d '{"email":"user@example.com","password":"secret"}'

curl -X GET localhost:8000/api/v1/profile \
  -H "Authorization: Bearer <token>"

# API key auth
curl -X GET localhost:8000/services/data \
  -H "X-API-Key: dev_key_12345"
```

---

## Security Best Practices

1. **Never commit secrets** - Use environment variables for API keys, client secrets
2. **Use HTTPS in production** - Set `secure: true` for cookies
3. **Rotate tokens** - Implement token refresh and revocation
4. **Hash API keys** - Store hashed keys in database (see API Key example)
5. **Validate user input** - Always sanitize and validate
6. **Implement rate limiting** - Use `Throttle` middleware
7. **Enable CSRF protection** - For cookie-based routes
8. **Use strong passwords** - Implement password hashing (bcrypt, argon2)

---

## Architecture

Wisp Security is built on **Symfony Security**:

- **TokenStorage**: Stores authenticated user
- **AuthorizationChecker**: Checks permissions via voters
- **Voters**: RoleVoter, PermissionVoter, RouteVoter
- **CSRF**: Symfony's CsrfTokenManager

**Wisp wraps Symfony** for an ergonomic API while maintaining full Symfony power under the hood.
