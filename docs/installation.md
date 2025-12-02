# Wisp Bundle Installation Guide

This guide documents every step to create a new Symfony 8 application with Wisp.

## 1. Create Symfony Skeleton

```bash
composer create-project symfony/skeleton:^8.0 my-app
cd my-app
```

## 2. Install Wisp Bundle

For published package:

```bash
composer require anders/wisp
```

For local development (symlink):

```json
// Add to composer.json
{
   "repositories": [
      {
         "type": "path",
         "url": "../wisp",
         "options": { "symlink": true }
      }
   ],
   "require": {
      "anders/wisp": "@dev"
   }
}
```

Then run:

```bash
composer update
```

## 3. Register Bundle

Add to `config/bundles.php`:

```php
<?php

return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Symfony\Bundle\SecurityBundle\SecurityBundle::class => ['all' => true],
    Wisp\Bundle::class => ['all' => true],
];
```

## 4. Configure Wisp

Create `config/packages/wisp.yaml`:

```yaml
wisp:
   runtime:
      version: '1.0.0'
      default_stage: dev
      default_debug: true
      detect_stage_from_cli: true
      detect_debug_from_cli: true

   envelope:
      enabled: true
      include_debug_info: true

   throttle:
      enabled: true
      limit: 60
      interval: 60
      strategy: ip

when@test:
   wisp:
      throttle:
         enabled: false
```

## 5. Configure Security

Update `config/packages/security.yaml`:

```yaml
security:
    password_hashers:
        Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface: 'auto'

    providers:
        app_user_provider:
            id: App\Security\UserProvider

    firewalls:
        dev:
            pattern: ^/(_profiler|_wdt|assets|build)/
            security: false
        api:
            pattern: ^/api
            stateless: true
            custom_authenticators:
                - App\Security\ApiTokenAuthenticator
        main:
            lazy: true

    access_control: []
```

## 6. Create User Entity

Create `src/Entity/User.php`:

```php
<?php

namespace App\Entity;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class User implements UserInterface, PasswordAuthenticatedUserInterface
{
   public function __construct (
      private string $id,
      private string $email,
      private string $password,
      private array $roles = []
   ) {}

   public function getId (): string { return $this->id; }
   public function getEmail (): string { return $this->email; }
   public function getUserIdentifier (): string { return $this->email; }
   public function getPassword (): string { return $this->password; }
   public function getRoles (): array {
      return array_unique ([ ...$this->roles, 'ROLE_USER' ]);
   }
   public function eraseCredentials (): void {}
}
```

## 7. Create User Provider

Create `src/Security/UserProvider.php`:

```php
<?php

namespace App\Security;

use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class UserProvider implements UserProviderInterface
{
   public function loadUserByIdentifier (string $identifier): UserInterface
   {
      throw new \RuntimeException ('Use token authenticator');
   }

   public function refreshUser (UserInterface $user): UserInterface
   {
      throw new UnsupportedUserException ('Stateless API');
   }

   public function supportsClass (string $class): bool
   {
      return true;
   }
}
```

## 8. Create Token Authenticator

Create `src/Security/ApiTokenAuthenticator.php`:

```php
<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class ApiTokenAuthenticator extends AbstractAuthenticator
{
   public function supports (Request $request): ?bool
   {
      return $request->headers->has ('Authorization');
   }

   public function authenticate (Request $request): Passport
   {
      $authHeader = $request->headers->get ('Authorization');

      if (!$authHeader || !str_starts_with ($authHeader, 'Bearer ')) {
         throw new CustomUserMessageAuthenticationException ('Invalid authorization header');
      }

      $token = substr ($authHeader, 7);

      // Validate token and load user (implement your logic here)
      // For demo purposes, use hardcoded tokens

      return new SelfValidatingPassport (
         new UserBadge ($token, function () use ($token) {
            // Load user by token from database
            return new User ('1', 'user@example.com', '', [ 'ROLE_USER' ]);
         })
      );
   }

   public function onAuthenticationSuccess (Request $request, TokenInterface $token, string $firewallName): ?Response
   {
      return null;
   }

   public function onAuthenticationFailure (Request $request, AuthenticationException $exception): ?Response
   {
      return new JsonResponse ([ 'error' => $exception->getMessage () ], 401);
   }
}
```

## 9. Create Your First Controller

Create `src/Controller/UserController.php`:

```php
<?php

namespace App\Controller;

use Wisp\Attribute\Is;
use Wisp\Http\Request;
use Wisp\Http\Response;
use Symfony\Component\Routing\Attribute\Route;

class UserController
{
   #[Route ('/api/users', methods: [ 'GET' ])]
   #[Is ('ROLE_USER')]
   public function index (Request $request): Response
   {
      return (new Response)->json ([
         'users' => [],
         'page' => $request->input ('page', 1)
      ]);
   }

   #[Route ('/api/health', methods: [ 'GET' ])]
   public function health (): Response
   {
      return (new Response)->json ([ 'status' => 'ok' ]);
   }
}
```

## 10. Install Test Dependencies

```bash
composer require --dev phpunit/phpunit symfony/browser-kit symfony/dom-crawler fakerphp/faker
```

## 11. Create Tests

Create `tests/Feature/UserTest.php`:

```php
<?php

namespace App\Tests\Feature;

use Wisp\Testing\TestCase;

class UserTest extends TestCase
{
   public function test_health_check (): void
   {
      $this->get ('/api/health')
         ->assertOk ()
         ->assertJsonPath ('data.status', 'ok');
   }
}
```

## 12. Run the Application

Clear cache and start the server:

```bash
php bin/console cache:clear
php -S localhost:8000 -t public
```

Test with curl:

```bash
# Health check
curl http://localhost:8000/api/health

# Authenticated request
curl -H "Authorization: Bearer your-token" http://localhost:8000/api/users
```

## 13. Run Tests

```bash
php bin/phpunit
```

## Configuration Reference

### Runtime Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| version | string | '1.0.0' | API version for envelope |
| default_stage | enum | 'prod' | dev, test, prod |
| default_debug | bool | false | Enable debug mode |
| detect_stage_from_cli | bool | true | Detect --stage flag |
| detect_debug_from_cli | bool | true | Detect --debug flag |
| hostname_map | array | [] | Map hostnames to stages |

### Envelope Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| enabled | bool | false | Wrap all JSON responses |
| include_debug_info | bool | true | Include timing/memory |

### Throttle Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| enabled | bool | false | Enable global throttling |
| limit | int | 60 | Requests per interval |
| interval | int | 60 | Interval in seconds |
| strategy | enum | 'ip' | ip, user, ip_user, route, ip_route |
