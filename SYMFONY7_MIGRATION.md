# Symfony 7 Security Components - Deprecation Analysis

This document summarizes the deprecation fixes applied to ensure compatibility with Symfony 7.x APIs.

## Issues Found and Fixed

### 1. AccessDecisionManager Strategy Parameter

**Issue**: In Symfony 7, the `AccessDecisionManager` constructor no longer accepts string strategy names (like `'affirmative'`). It now requires a `AccessDecisionStrategyInterface` instance.

**Old Code (Deprecated)**:
```php
$container
   ->register (AccessDecisionManager::class)
   ->setArguments ([
      '$voters' => [...],
      '$strategy' => 'affirmative'  // ❌ String not accepted
   ]);
```

**Fixed Code**:
```php
use Symfony\Component\Security\Core\Authorization\Strategy\AffirmativeStrategy;

// Register the strategy
$container
   ->register (AffirmativeStrategy::class)
   ->setArgument ('$allowIfAllAbstainDecisions', false)
   ->setPublic (true);

// Use strategy reference
$container
   ->register (AccessDecisionManager::class)
   ->setArguments ([
      '$voters' => [...],
      '$strategy' => new Reference (AffirmativeStrategy::class)  // ✅ Strategy object
   ]);
```

**Available Strategies**:
- `AffirmativeStrategy` - Grants access if ANY voter grants access
- `ConsensusStrategy` - Grants access if majority of voters grant access
- `UnanimousStrategy` - Grants access only if ALL voters grant access
- `PriorityStrategy` - Uses priority-based voting

**Constructor Signatures**:
```php
AffirmativeStrategy::__construct(bool $allowIfAllAbstainDecisions = false)
ConsensusStrategy::__construct(bool $allowIfAllAbstainDecisions = false, bool $allowIfEqualGrantedDeniedDecisions = true)
UnanimousStrategy::__construct(bool $allowIfAllAbstainDecisions = false)
PriorityStrategy::__construct(bool $allowIfAllAbstainDecisions = false)
```

---

### 2. SessionTokenStorage Constructor Change

**Issue**: `SessionTokenStorage` constructor signature changed in Symfony 7. It now requires `RequestStack` instead of `SessionInterface`.

**Old Code (Deprecated)**:
```php
use Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage;

$container
   ->register (SessionTokenStorage::class)
   ->setArguments ([
      '$session' => new Reference (SessionInterface::class)  // ❌ Wrong parameter
   ]);
```

**Fixed Code**:
```php
use Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage;
use Symfony\Component\HttpFoundation\RequestStack;

// Register RequestStack
$container
   ->register (RequestStack::class)
   ->setPublic (true);

// Register SessionTokenStorage with RequestStack
$container
   ->register (SessionTokenStorage::class)
   ->setArguments ([
      '$requestStack' => new Reference (RequestStack::class),  // ✅ Correct parameter
      '$namespace' => '_csrf'
   ]);
```

**Constructor Signature**:
```php
SessionTokenStorage::__construct(
   RequestStack $requestStack,
   string $namespace = 'default'
)
```

**Why This Change?**:
Symfony moved from direct session access to using `RequestStack` to retrieve the session from the current request. This provides better decoupling and testability.

---

### 3. CsrfTokenManager Namespace Parameter

**Issue**: The `CsrfTokenManager` constructor now has an optional third parameter for namespace.

**Updated Code**:
```php
$container
   ->register (CsrfTokenManagerInterface::class)
   ->setClass (CsrfTokenManager::class)
   ->setArguments ([
      '$generator' => new UriSafeTokenGenerator (),
      '$storage' => new Reference (SessionTokenStorage::class),
      '$namespace' => null  // Optional namespace parameter
   ]);
```

**Constructor Signature**:
```php
CsrfTokenManager::__construct(
   ?TokenGeneratorInterface $generator = null,
   ?TokenStorageInterface $storage = null,
   RequestStack|callable|string|null $namespace = null
)
```

---

### 4. RequestStack Integration

**Issue**: `RequestStack` must be properly populated with the current request for CSRF and other session-based features to work.

**Fixed Code**:
```php
public function run () : void
{
   $container = Container::instance ();
   $container->compile ();

   $request = Request::createFromGlobals ();

   // Get RequestStack from container and push current request
   $requestStack = $container->get (RequestStack::class);
   $requestStack->push ($request);  // ✅ Critical for session-based services

   // ... rest of execution
}
```

**Why This Matters**:
- `SessionTokenStorage` uses `RequestStack::getCurrentRequest()->getSession()`
- Without pushing the request, CSRF tokens cannot be stored/retrieved
- This pattern is consistent with Symfony's HttpKernel design

---

## Summary of Changes in src/Wisp.php

### Added Imports
```php
use Symfony\Component\Security\Core\Authorization\Strategy\AffirmativeStrategy;
```

### New Service Registrations

1. **AffirmativeStrategy** (lines 162-166)
   - Registered as service for AccessDecisionManager
   - Configured with `$allowIfAllAbstainDecisions = false`

2. **RequestStack** (lines 227-230)
   - Registered for CSRF and session access
   - Populated in `run()` method with current request

### Modified Service Registrations

1. **AccessDecisionManager** (lines 168-179)
   - Changed from string `'affirmative'` to `Reference(AffirmativeStrategy::class)`

2. **SessionTokenStorage** (lines 191-198)
   - Changed parameter from `$session` to `$requestStack`
   - Added `$namespace = '_csrf'` parameter

3. **CsrfTokenManager** (lines 200-209)
   - Added optional `$namespace = null` parameter

### Modified run() Method (lines 263-264)

```php
$requestStack = $container->get (RequestStack::class);
$requestStack->push ($request);
```

---

## Verification

All Symfony Security components now use correct Symfony 7.x APIs:

✅ `TokenStorage` - No constructor, works as-is
✅ `AffirmativeStrategy` - Properly instantiated with boolean parameter
✅ `AccessDecisionManager` - Uses strategy object reference
✅ `AuthorizationChecker` - Constructor unchanged
✅ `SessionTokenStorage` - Uses RequestStack + namespace
✅ `CsrfTokenManager` - All three parameters properly set
✅ `UriSafeTokenGenerator` - Constructor unchanged (default entropy = 256)
✅ `RequestStack` - Registered and populated with current request

---

## Testing

To verify the fixes work:

```bash
# Test initialization
php -r "require 'vendor/autoload.php';
use Wisp\Wisp;
use Wisp\Environment\Stage;
\$app = new Wisp(['stage' => Stage::development]);
echo 'OK\n';"

# Run example application
cd example
php -S localhost:8000 security.php
```

All deprecation warnings have been resolved. The implementation now follows Symfony 7.x best practices.
