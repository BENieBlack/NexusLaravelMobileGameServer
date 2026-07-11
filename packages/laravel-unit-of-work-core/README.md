# Laravel Unit of Work Core

Unit of Work pattern implementation for Laravel with batch operations, query optimization, and transaction management.

## Features

- **Unit of Work Pattern**: Batch INSERT/UPDATE/DELETE operations for optimal performance
- **Query Optimization**: Deferred execution with batch processing
- **Transaction Management**: Automatic transaction handling with QueryManager
- **Database Sharding**: Abstract player session interface for sharding support
- **Batch Operations**: Efficient bulk inserts, updates, and deletes
- **Operation Queueing**: Collect and execute database operations in batches

## Installation

Add the package to your Laravel project:

```bash
composer require laravel-mobile-rpg/unit-of-work-core
```

This package depends on `laravel-mobile-rpg/persistence-core` which will be installed automatically.

The service provider will be automatically registered via Laravel's package discovery.

Publish the configuration file:

```bash
php artisan vendor:publish --tag=unit-of-work-config
```

## Configuration

Edit `config/unit-of-work.php` to customize:

- Database connections for different data types (trx, log, sys, mst)
- Cache settings (driver, TTL, prefix)
- Batch execution limits
- Query logging options

## Usage

### Basic Unit of Work Pattern

```php
use LaravelUnitOfWork\Contracts\QueryManagerInterface;
use LaravelPersistence\Repositories\Trx\TrxPlayerRepository;

// Get the QueryManager instance
$queryManager = app(QueryManagerInterface::class);

// Make changes to models via repositories
$playerRepo = new TrxPlayerRepository();
$player = $playerRepo->queryOrMemory()->first();
$player->level = 10;
$playerRepo->setModel($player); // Queued for batch execution

// Execute all queued operations in a transaction
$queryManager->flush();
```

### Repository Integration

Your repository classes (extending from `laravel-persistence-core`) will automatically integrate with the QueryManager when you use the Unit of Work trait:

```php
use LaravelPersistence\Repositories\Trx\_BaseTrxRepository;
use LaravelUnitOfWork\Traits\UsesUnitOfWork;

class TrxPlayerRepository extends _BaseTrxRepository
{
    use UsesUnitOfWork; // Automatically registers with QueryManager
    
    protected string $modelClass = TrxPlayer::class;
    protected array $uniqueKeys = ['sys_player_id'];
}
```

### Player Session Resolver

Implement the `PlayerSessionResolverInterface` in your application to provide player session information and database sharding logic:

```php
use LaravelUnitOfWork\Contracts\PlayerSessionResolverInterface;

class ApiSession implements PlayerSessionResolverInterface
{
    public static function hasSysPlayerId(): bool
    {
        return static::$sysPlayerId !== null;
    }

    public static function getSysPlayerId(): int
    {
        return static::$sysPlayerId;
    }

    public static function setSysPlayerId(int $sysPlayerId): void
    {
        static::$sysPlayerId = $sysPlayerId;
    }

    public static function getConnectionName(string $baseConnection): string
    {
        // Implement sharding logic here
        $playerId = static::getSysPlayerId();
        $shardId = ($playerId % 2) + 1;
        return "{$baseConnection}{$shardId}";
    }
}
```

Register the implementation in your `AppServiceProvider`:

```php
$this->app->bind(
    PlayerSessionResolverInterface::class,
    ApiSession::class
);
```

## Architecture

### QueryManager Flow

1. **Registration**: Repositories register themselves with QueryManager on first `setModel()` call
2. **Queueing**: Models are queued in memory until `flush()` is called
3. **Collection**: OperationCollector organizes operations by connection/table
4. **Execution**: BatchExecutor runs optimized batch queries in transactions
5. **Hooks**: After-save hooks are called for logging/events
6. **Cleanup**: All queues are cleared after successful execution

### Batch Operations

The QueryManager optimizes operations by:
- **Grouping**: Operations grouped by connection and table
- **Batching**: INSERTs combined into multi-row statements
- **Ordering**: UPDATEs before DELETEs, INSERTs first
- **Transactions**: All operations wrapped in database transactions

### Components

- **QueryManager**: Main coordinator for Unit of Work pattern
- **OperationCollector**: Collects and organizes operations from repositories
- **BatchExecutor**: Executes batch INSERT/UPDATE/DELETE operations
- **UpdateQueryBuilder**: Builds optimized UPDATE queries with relative changes

## Requirements

- PHP 8.1 or higher
- Laravel 10.x or 11.x
- laravel-mobile-rpg/utilities package
- laravel-mobile-rpg/persistence-core package

## License

MIT License

## Credits

Developed for Laravel mobile RPG server architecture.
