# Laravel Utilities

Laravel utility classes for common operations.

## Installation

Add this package to your Laravel project:

```bash
composer require s-nakamura/laravel-utilities
```

For local development (monorepo), add to your `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../packages/laravel-utilities"
        }
    ],
    "require": {
        "s-nakamura/laravel-utilities": "*"
    }
}
```

## Components

### ClockUtility

Fixed time management utility for testing.

```php
use LaravelUtilities\ClockUtility;

// Initialize the clock (fixes the current time)
ClockUtility::initialize();

// Get current time as CarbonImmutable
$now = ClockUtility::now();

// Get current time as string (Y-m-d H:i:s format)
$nowString = ClockUtility::nowToString();
```

**Use Cases:**
- Consistent timestamps during a request lifecycle
- Easier testing with fixed time
- Prevents time-based race conditions

### RedisUtility

Redis cache operations without repeatedly calling `store('redis')`.

```php
use LaravelUtilities\RedisUtility;

// Basic operations
RedisUtility::put('key', 'value', 3600);
$value = RedisUtility::get('key');
RedisUtility::forget('key');

// Check existence
if (RedisUtility::has('key')) {
    // Key exists
}

// Remember pattern
$data = RedisUtility::remember('expensive_key', 3600, function() {
    return expensiveCalculation();
});

// Compressed cache (for large data)
RedisUtility::putCompressed('large_data', $largeArray, 3600);
$data = RedisUtility::getCompressed('large_data');

// Increment/Decrement
RedisUtility::increment('counter', 1);
RedisUtility::decrement('counter', 1);

// Multiple operations
RedisUtility::putMany(['key1' => 'value1', 'key2' => 'value2'], 3600);
$values = RedisUtility::many(['key1', 'key2']);
```

**Available Methods:**
- `put()`, `get()`, `forget()`, `has()`, `forever()`
- `add()`, `increment()`, `decrement()`
- `remember()`, `rememberForever()`, `pull()`
- `many()`, `putMany()`, `deleteMany()`
- `putCompressed()`, `getCompressed()`, `rememberCompressed()`
- `ttl()`, `expire()`, `keys()`, `prefixKey()`
- `flush()`, `clear()`

## Requirements

- PHP 8.2 or higher
- Laravel 11.0 or 12.0
- Carbon 2.0 or 3.0

## License

MIT
