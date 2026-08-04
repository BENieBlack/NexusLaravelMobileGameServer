# Laravel Persistence Core

Base Model and Repository classes for Laravel RPG applications with support for multiple database types and caching strategies.

## Features

- **Hierarchical Model Base Classes**: Specialized base models for different data types
- **Repository Pattern**: Base repository classes with memory caching
- **Multiple Data Types**: Support for Trx, Log, Sys, and Mst data
- **Memory Caching**: In-memory model caching to avoid duplicate queries
- **Redis Caching**: Redis caching support for master data
- **Flexible Architecture**: Override static methods for custom session handling
- **Type Safety**: Full interface support for type hinting
- **CustomCollection**: Performance-optimized collection class for large datasets

## Installation

Add the package to your Laravel project:

```bash
composer require laravel-mobile-rpg/persistence-core
```

The service provider will be automatically registered via Laravel's package discovery.

## Model Hierarchy

```
_BaseModel (abstract)
├── Trx\_BaseTrx - Transaction data (player-specific, mutable)
├── Log\_BaseLog - Log data (INSERT only, immutable)
├── Sys\_BaseSys - System data (shared across shards)
└── Mst\_BaseMst - Master data (read-only, cached)
```

## Usage

### Creating Models

Extend the appropriate base model for your data type:

```php
use LaravelPersistence\Models\Trx\_BaseTrx;

class TrxPlayer extends _BaseTrx
{
    protected $table = 'trx_player';
    protected $connection = 'trx';
    protected string $selectKey = 'sys_player_id';
    protected array $uniqueKeys = ['sys_player_id'];
    
    protected $fillable = [
        'sys_player_id',
        'player_name',
        'level',
        'exp',
    ];
}
```

### Creating Repositories

Extend the appropriate base repository:

```php
use LaravelPersistence\Repositories\Trx\_BaseTrxRepository;

class TrxPlayerRepository extends _BaseTrxRepository
{
    protected string $modelClass = TrxPlayer::class;
    protected array $uniqueKeys = ['sys_player_id'];
    
    // Override static methods for session handling
    protected static function hasSysPlayerId(): bool
    {
        return ApiSession::hasSysPlayerId();
    }
    
    protected static function getSysPlayerId(): int
    {
        return ApiSession::getSysPlayerId();
    }
    
    protected static function setSysPlayerId(int $sysPlayerId): void
    {
        ApiSession::setSysPlayerId($sysPlayerId);
    }
}
```

### Using Repositories

```php
// Get repository instance
$playerRepo = new TrxPlayerRepository();

// Query or get from memory cache
$players = $playerRepo->queryOrMemory();

// Get by player ID
$player = $playerRepo->getMapBySysPlayerId($playerId)->first();

// Set model (queues for batch save)
$player->level = 10;
$playerRepo->setModel($player);
```

## Data Types

### Trx (Transaction Data)
- Player-specific transactional data
- Mutable, supports updates
- Memory cached per request
- Example: player inventory, equipment, currency

### Log (Log Data)
- Audit and activity logs
- INSERT only (immutable)
- No caching (historical data)
- Example: login logs, purchase logs, action logs

### Sys (System Data)
- Cross-shard system data
- Mutable, less frequently updated
- Memory or Redis caching
- Example: player accounts, friend lists, maintenance info

### Mst (Master Data)
- Read-only game configuration
- Redis caching with configurable TTL
- Versioned by deploy_key
- Example: item definitions, level requirements, gacha rates

## Repository Methods

### Common Methods (all repositories)

```php
// Get all data (from memory cache or DB)
$collection = $repository->queryOrMemory();

// Set model (queue for save)
$repository->setModel($model);

// Get queued models
$queued = $repository->getQueuedModels();

// Clear queue
$repository->clearQueue();
```

### Trx Repository Methods

```php
// Get by player ID (as Collection)
$players = $repository->getMapBySysPlayerId($playerId);

// Get by player ID (as array)
$players = $repository->getBySysPlayerId($playerId);
```

### Mst Repository Methods

```php
// Get by ID
$item = $mstItemRepository->selectById($itemId);

// Get multiple by IDs
$items = $mstItemRepository->selectListByIds([1, 2, 3]);

// Clear cache
$mstItemRepository->clearCache();
```

### Log Repository Methods

```php
// Get by ID
$log = $logRepository->getById($logId);
```

### Sys Repository Methods

```php
// Get by ID
$account = $sysPlayerRepository->selectById($playerId);
```

## Session Handling

The base repositories use static methods for session handling that must be overridden:

```php
protected static function hasSysPlayerId(): bool;
protected static function getSysPlayerId(): int;
protected static function setSysPlayerId(int $sysPlayerId): void;
```

Override these in your concrete repository classes or create a base repository in your application that all repositories extend.

## Configuration

No configuration file is required for this package. All settings are inherited from your Laravel database and cache configurations.

## Requirements

- PHP 8.1 or higher
- Laravel 10.x or 11.x
- laravel-mobile-rpg/utilities package

## License

MIT License

## Credits

Developed for Laravel mobile RPG server architecture.
