# Wisp

Ergonomic Symfony 8 bundle for building APIs.

## Requirements

- PHP 8.4+
- Symfony 8.0+

## Installation

```bash
composer require anders/wisp
```

Register the bundle in `config/bundles.php`:

```php
return [
    // ...
    Wisp\Bundle::class => ['all' => true],
];
```

## Configuration

Create `config/packages/wisp.yaml`:

```yaml
wisp:
   runtime:
      version: '1.0.0'
      default_stage: dev       # dev, test, prod
      default_debug: true

   envelope:
      enabled: true
      include_debug_info: true

   throttle:
      enabled: true
      limit: 60
      interval: 60
      strategy: ip             # ip, user, ip_user, route, ip_route
```

## Features

### Ergonomic Request

Type-hint `Wisp\Http\Request` in your controllers:

```php
use Wisp\Http\Request;
use Wisp\Http\Response;

class UserController
{
   public function index (Request $request): Response
   {
      $page = $request->input ('page', 1);
      $token = $request->bearerToken ();
      $ip = $request->ip ();

      return (new Response)->json ([ 'page' => $page ]);
   }
}
```

### Fluent Response

```php
use Wisp\Http\Response;

// JSON response
return (new Response)->json ([ 'data' => $users ]);

// With status code
return (new Response)->status (201)->json ([ 'id' => 1 ]);

// Error response
return (new Response)->error ('Not found', 404);

// With caching
return (new Response)->json ($data)->cache (3600);
```

### Role Guards

```php
use Wisp\Attribute\Is;

class AdminController
{
   #[Is ('ROLE_ADMIN')]
   public function dashboard (): Response
   {
      // Only accessible by admins
   }
}
```

### Permission Guards

```php
use Wisp\Attribute\Can;

class PostController
{
   #[Can ('edit', subject: 'post')]
   public function edit (Post $post): Response
   {
      // Requires 'edit' permission on 'post'
   }
}
```

### Rate Limiting

```php
use Wisp\Attribute\Throttle;

class AuthController
{
   #[Throttle (limit: 5, interval: 60)]
   public function login (): Response
   {
      // 5 requests per minute
   }
}
```

### DTO Validation

```php
use Symfony\Component\Validator\Constraints as Assert;
use Wisp\Attribute\Validated;

class CreateUserRequest
{
   #[Assert\NotBlank]
   #[Assert\Email]
   public string $email;

   #[Assert\Length (min: 8)]
   public string $password;
}

class UserController
{
   public function store (#[Validated] CreateUserRequest $dto): Response
   {
      // $dto is validated automatically
      return (new Response)->status (201)->json ([
         'email' => $dto->email
      ]);
   }
}
```

### Response Envelope

When enabled, all JSON responses are wrapped:

```json
{
   "version": "1.0.0",
   "status": 200,
   "stage": "dev",
   "timestamp": "2024-01-15T10:30:00Z",
   "debug": {
      "elapsed": 0.0234,
      "memory": "12.5 MB"
   },
   "meta": {
      "method": "GET",
      "path": "/api/users"
   },
   "data": {
      "users": []
   }
}
```

## Testing

Extend `Wisp\Testing\TestCase` for fluent API testing:

```php
use Wisp\Testing\TestCase;

class UserTest extends TestCase
{
   public function test_list_users (): void
   {
      $this->withToken ('valid-token')
         ->get ('/api/users')
         ->assertOk ()
         ->assertJsonHas ('data.users')
         ->assertJsonCount (2, 'data.users');
   }

   public function test_requires_auth (): void
   {
      $this->get ('/api/users')
         ->assertUnauthorized ();
   }
}
```

### Fixture Factory

Generate test data from validation constraints:

```php
use Wisp\Testing\FixtureFactory;

$fixtures = new FixtureFactory ();

// Generate single fixture
$data = $fixtures->create (CreateUserRequest::class);
// ['email' => 'random@example.com', 'password' => 'a8kD2mNp9x']

// With overrides
$data = $fixtures->create (CreateUserRequest::class, [
   'email' => 'test@example.com'
]);

// Generate multiple
$users = $fixtures->createMany (CreateUserRequest::class, 5);
```

## License

MIT
