# Security Fixes Applied

This document tracks the critical security fixes that have been implemented.

## ✅ CRITICAL FIXES COMPLETED

### 1. Session Fixation Vulnerability - FIXED ✅

**File**: `src/Middleware/Session.php`

**Changes**:
- Added `regenerate()` method to regenerate session ID after login
- Prevents session fixation attacks by destroying old session data

**Usage**:
```php
// In your login controller:
$this->sessionMiddleware->regenerate (true);
```

**Example**: See `example/src/Controller/AuthController.php:115`

---

### 2. Insecure Cookie Defaults - FIXED ✅

**File**: `src/Middleware/Session.php`

**Changes**:
- Changed default cookie name to `__Host-wisp_session` (prevents subdomain attacks)
- Changed `Secure` flag default from `false` to `true`
- Made `SameSite` configurable (defaults to `Lax`)
- All security settings now configurable via constructor parameters

**Configuration**:
```php
$app->middleware (Session::class, [
   'secure' => true,        // Default: true (requires HTTPS)
   'sameSite' => 'Strict',  // Default: Lax
   'cookieName' => '__Host-wisp_session'  // Default
]);
```

**Breaking Change**: Sessions now require HTTPS by default. For local dev, set `'secure' => false`.

---

### 3. Password Hashing - IMPLEMENTED ✅

**New File**: `src/Security/PasswordHasher.php`

**Features**:
- Wraps Symfony's PasswordHasher for secure bcrypt/argon2 hashing
- Automatic salt generation
- Constant-time password verification
- Password rehashing detection (when algorithm/cost changes)

**Usage**:
```php
use Wisp\Security\PasswordHasher;

$hasher = new PasswordHasher ();

// Hash a password
$hash = $hasher->hash ('my-password');
// Returns: $2y$12$...

// Verify a password
if ($hasher->verify ('my-password', $hash)) {
   // Correct password
}

// Check if rehashing needed
if ($hasher->needsRehash ($hash)) {
   $newHash = $hasher->hash ($password);
   // Update database
}
```

**Integration**:
- Registered in DI container (see `src/Wisp.php:218-221`)
- Example usage in `example/src/Controller/AuthController.php`

**Algorithms Supported**:
- `auto` (default - PHP chooses best)
- `bcrypt` (cost: 12)
- `argon2i` (memory: 65536, time: 4, threads: 1)
- `argon2id` (memory: 65536, time: 4, threads: 1)

---

### 4. OAuth State Validation - FIXED ✅

**File**: `example/src/Controller/OAuthController.php`

**Changes**:
- Added `hash_equals()` for constant-time state comparison
- Added checks for empty state values
- Improved error message to indicate CSRF protection

**Before**:
```php
if (empty ($state) || $state !== $sessionState) {
```

**After**:
```php
if (empty ($state) || empty ($sessionState) || !hash_equals ($sessionState, $state)) {
```

This prevents timing attacks during state validation.

---

## 🟡 REMAINING HIGH-PRIORITY ISSUES

### 5. No Brute Force Protection

**Status**: NOT IMPLEMENTED

**Risk**: HIGH

**Issue**: No account-level lockout or progressive delays. Attackers can attempt unlimited login attempts.

**Recommended Fix**:
```php
// Pseudo-code
class LoginThrottle
{
   private function checkFailedAttempts (string $identifier) : bool
   {
      $attempts = $this->cache->get ("login_attempts:{$identifier}");

      if ($attempts >= 5) {
         $lockoutTime = $this->cache->get ("login_lockout:{$identifier}");
         if ($lockoutTime > time ()) {
            throw new TooManyAttemptsException ();
         }
      }

      return true;
   }

   private function recordFailedAttempt (string $identifier) : void
   {
      $attempts = $this->cache->increment ("login_attempts:{$identifier}");

      if ($attempts >= 5) {
         $this->cache->set ("login_lockout:{$identifier}", time () + 900); // 15 min
      }
   }
}
```

**Where to Implement**: New middleware or integrate into authentication controllers

---

### 6. CacheSessionStorage Extends MockArraySessionStorage

**Status**: NOT FIXED

**Risk**: MEDIUM

**Issue**: `src/Session/CacheSessionStorage.php:9` extends `MockArraySessionStorage` which is designed for testing, not production.

**Recommended Fix**:
```php
// Change from:
class CacheSessionStorage extends MockArraySessionStorage

// To:
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageInterface;

class CacheSessionStorage implements SessionStorageInterface
{
   // Implement all required methods
}
```

**Impact**: Architectural cleanup, no functional change (overridden methods work correctly)

---

### 7. JSON Parsing Vulnerabilities

**Status**: NOT FIXED

**Risk**: MEDIUM

**Issue**: `src/Http/Request.php:56` - No depth limits, error handling, or size limits on JSON parsing

**Current Code**:
```php
$data = json_decode ($this->getContent (), true);
return $data ?? [];
```

**Recommended Fix**:
```php
try {
   $content = $this->getContent ();

   // Limit content size (e.g., 1MB)
   if (strlen ($content) > 1048576) {
      throw new \RuntimeException ('Request body too large');
   }

   // Use JSON_THROW_ON_ERROR and depth limit
   $data = json_decode ($content, true, 512, JSON_THROW_ON_ERROR);
   return $data;
} catch (\JsonException $e) {
   throw new \RuntimeException ('Invalid JSON: ' . $e->getMessage ());
}
```

---

### 8. API Keys in Query Parameters

**Status**: DOCUMENTATION WARNING NEEDED

**Risk**: MEDIUM

**Issue**: `src/Middleware/Authentication/ApiKeyAuthentication.php:35` allows API keys in query strings

**Problem**: Query parameters are logged in:
- Web server access logs
- Browser history
- Proxy logs
- Referer headers

**Recommended Fix**:
Add strong warning to documentation:

```php
/**
 * SECURITY WARNING: Do NOT use query parameter authentication in production!
 * Query strings are logged everywhere and expose your API keys.
 *
 * Use header-based authentication instead:
 * curl -H "X-API-Key: your-key" https://api.example.com
 */
```

Or remove query parameter support entirely.

---

## 🟢 MEDIUM PRIORITY ISSUES

### 9. Constant-Time Comparisons

**Status**: PARTIALLY FIXED

**Fixed**: OAuth state validation now uses `hash_equals()`

**Still Needed**:
- API key validation callbacks should document `hash_equals()` usage
- Any custom token/secret comparison code

**Documentation Addition**:
```php
// In ApiKeyAuthentication docs:
'validator' => fn ($key) => {
   $storedKey = getApiKeyFromDatabase ();

   // SECURITY: Use constant-time comparison
   if (hash_equals ($storedKey, $key)) {
      return new User (...);
   }
   return null;
}
```

---

### 10. Rate Limit IP Spoofing

**Status**: NOT FIXED

**Risk**: MEDIUM

**Issue**: `src/Middleware/Throttle.php:25` uses `getClientIp()` which can be spoofed via headers

**Recommended Fix**:
Add documentation about trusted proxies:

```php
// In config/routing.php or similar:
Request::setTrustedProxies (
   ['10.0.0.0/8'], // Your load balancer IPs
   Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST
);
```

**Additional**: Consider using authenticated user ID for rate limiting instead of just IP.

---

## 📋 MIGRATION GUIDE FOR USERS

### Breaking Changes

1. **Session cookies now require HTTPS by default**
   - **Action**: Set `'secure' => false` for local development
   - **Production**: Ensure HTTPS is configured

2. **Cookie name changed to `__Host-wisp_session`**
   - **Impact**: Existing sessions will be invalidated
   - **Action**: Users will need to re-login after upgrade

3. **Password hashing now available**
   - **Action**: Update login controllers to use `PasswordHasher`
   - **Example**: See `example/src/Controller/AuthController.php`

### Upgrade Steps

1. **Update Session Middleware**:
```php
// Add to login controller:
$this->sessionMiddleware->regenerate (true);
```

2. **Update Password Handling**:
```php
// Old (INSECURE):
if ($password === $user->password) { }

// New (SECURE):
$hasher = new PasswordHasher ();
if ($hasher->verify ($password, $user->password_hash)) { }
```

3. **Configure Cookie Settings**:
```php
$app->middleware (Session::class, [
   'secure' => $_ENV ['APP_ENV'] === 'production',
   'sameSite' => 'Strict'
]);
```

---

## 🔍 TESTING CHECKLIST

- [x] Session fixation prevented (regenerate on login)
- [x] Cookies have Secure flag in production
- [x] Password hashing uses bcrypt with cost 12
- [x] OAuth state validated with constant-time comparison
- [ ] Brute force protection tested (TODO)
- [ ] JSON depth bomb attack prevented (TODO)
- [ ] Rate limiting cannot be bypassed (TODO)

---

## 📚 ADDITIONAL RESOURCES

### Security Headers Checklist

Already implemented in `Helmet` middleware:
- ✅ X-Frame-Options
- ✅ X-Content-Type-Options
- ✅ X-XSS-Protection
- ✅ Content-Security-Policy
- ✅ Strict-Transport-Security

### Recommended Next Steps

1. Implement brute force protection middleware
2. Add security event logging (login attempts, failures)
3. Add 2FA support
4. Create password strength validator
5. Add rate limiting per user account (not just IP)
6. Add audit trail for security-sensitive actions

---

**Last Updated**: 2025-01-XX
**Security Audit By**: Claude Code Security Review
