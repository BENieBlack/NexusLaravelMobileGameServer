# Laravel Security Middleware

Laravel security middleware package including client signature verification, idempotency guarantee, rate limiting, and access token verification.

## Installation

Add this package to your Laravel project:

```bash
composer require s-nakamura/laravel-security-middleware
```

For local development (monorepo), add to your `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../packages/laravel-security-middleware"
        }
    ],
    "require": {
        "s-nakamura/laravel-security-middleware": "*"
    }
}
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=laravel-security-middleware-config
```

## Middleware

### 1. VerifyClientSignature

Verifies that requests come from legitimate applications using HMAC-SHA256 signatures.

**Features:**
- HMAC signature verification (request body + timestamp + nonce)
- Timestamp validation (requests within 5 minutes by default)
- Nonce verification (prevents replay attacks)

**Request Headers:**
```
X-Client-Timestamp: {unix_timestamp}
X-Client-Nonce: {random_string}
X-Client-Signature: {hmac_sha256_signature}
```

**Signature Calculation:**
```
signature = HMAC-SHA256(
  key: CLIENT_SECRET,
  message: "{timestamp}:{nonce}:{request_body}"
)
```

**Usage:**
```php
// app/Http/Kernel.php or bootstrap/app.php
use LaravelSecurityMiddleware\Middleware\VerifyClientSignature;

// Add to middleware groups or routes
```

**Configuration:**
```env
CLIENT_SECRET=your-secret-key
CLIENT_SIGNATURE_TIMESTAMP_TOLERANCE=300  # 5 minutes
CLIENT_SIGNATURE_NONCE_CACHE_TTL=600      # 10 minutes
```

---

### 2. IdempotencyMiddleware

Guarantees request idempotency using the `X-Unique-Request-Identifier` header.

**Features:**
- Caches successful responses (2xx status codes)
- Returns cached response for duplicate requests
- Gzip compression to reduce memory usage
- Skips GET requests automatically

**Request Headers:**
```
X-Unique-Request-Identifier: {unique_id}
```

**Response Headers:**
```
X-Idempotency-Cache: HIT  # or MISS
```

**Usage:**
```php
use LaravelSecurityMiddleware\Middleware\IdempotencyMiddleware;

// Add to middleware groups or routes
```

**Configuration:**
```env
IDEMPOTENCY_ENABLED=true
IDEMPOTENCY_CACHE_PREFIX=idempotency
IDEMPOTENCY_CACHE_TTL=86400           # 24 hours
IDEMPOTENCY_COMPRESSION_LEVEL=6       # 1-9
```

---

### 3. ThrottleSignUp

Rate limiting for sign-up endpoints to prevent abuse.

**Features:**
- IP-based rate limiting (10 attempts per hour by default)
- Device ID-based rate limiting (3 attempts per hour by default)
- Configurable limits and time windows

**Response Headers:**
```
X-RateLimit-Limit: 10
X-RateLimit-Remaining: 5
```

**Error Response (429):**
```json
{
    "error": "TOO_MANY_REQUESTS",
    "message": "Too many sign up attempts from this IP address. Please try again later.",
    "retry_after": 3600
}
```

**Usage:**
```php
use LaravelSecurityMiddleware\Middleware\ThrottleSignUp;

// Add to sign-up route
Route::post('/sign_up', [AuthController::class, 'signUp'])
    ->middleware(ThrottleSignUp::class);
```

**Configuration:**
```env
THROTTLE_SIGNUP_ENABLED=true
THROTTLE_SIGNUP_MAX_ATTEMPTS_PER_IP=10
THROTTLE_SIGNUP_MAX_ATTEMPTS_PER_DEVICE=3
THROTTLE_SIGNUP_RATE_LIMIT_WINDOW=3600  # 1 hour
```

---

### 4. VerifyAccessToken

Verifies access tokens and adds player information to requests.

**Note:** This middleware requires implementation of two interfaces in your application:

#### Step 1: Implement `TokenValidatorInterface`

```php
namespace App\Domain\Auth\Services;

use LaravelSecurityMiddleware\Contracts\TokenValidatorInterface;

class TokenValidator implements TokenValidatorInterface
{
    public function validateAccessToken(string $token): ?array
    {
        // Your token validation logic
        // Return array with 'player_id' and 'uuid', or null if invalid
        
        return [
            'player_id' => 123,
            'uuid' => 'abc-def-ghi',
        ];
    }
}
```

#### Step 2: Implement `PlayerSessionInterface` (Optional)

```php
namespace App\Persistence;

use LaravelSecurityMiddleware\Contracts\PlayerSessionInterface;

class ApiSession implements PlayerSessionInterface
{
    public static function setPlayerId(int $playerId): void
    {
        // Set player ID for the current request
    }
}
```

#### Step 3: Register in Service Provider

```php
// app/Providers/AppServiceProvider.php
use LaravelSecurityMiddleware\Contracts\TokenValidatorInterface;
use LaravelSecurityMiddleware\Contracts\PlayerSessionInterface;

public function register(): void
{
    $this->app->bind(TokenValidatorInterface::class, TokenValidator::class);
    $this->app->bind(PlayerSessionInterface::class, ApiSession::class);
}
```

**Usage:**
```php
use LaravelSecurityMiddleware\Middleware\VerifyAccessToken;

// Add to authenticated routes
Route::middleware([VerifyAccessToken::class])->group(function () {
    // Your protected routes
});
```

**Request Headers:**
```
Authorization: Bearer {access_token}
```

**Request Data (added by middleware):**
```php
$request->input('authenticated_player_id'); // 123
$request->input('authenticated_uuid');      // 'abc-def-ghi'
```

---

## Complete Example

```php
// app/Http/Kernel.php or bootstrap/app.php
use LaravelSecurityMiddleware\Middleware\{
    VerifyClientSignature,
    IdempotencyMiddleware,
    ThrottleSignUp,
    VerifyAccessToken
};

// For all API routes
->withMiddleware(function (Middleware $middleware) {
    $middleware->group('api', [
        VerifyClientSignature::class,
    ]);
    
    // For authenticated routes
    $middleware->alias([
        'auth.api' => [
            VerifyAccessToken::class,
            IdempotencyMiddleware::class,
        ],
    ]);
});

// routes/api.php
Route::post('/sign_up', [AuthController::class, 'signUp'])
    ->middleware([ThrottleSignUp::class]);

Route::middleware(['auth.api'])->group(function () {
    Route::get('/player/profile', [PlayerController::class, 'getProfile']);
    Route::post('/gacha/draw', [GachaController::class, 'draw']);
});
```

## Configuration

All configuration can be found in `config/security.php` after publishing.

## Requirements

- PHP 8.2 or higher
- Laravel 11.0 or 12.0
- Redis (for caching)

## License

MIT
