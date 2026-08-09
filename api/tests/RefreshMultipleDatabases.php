<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;

trait RefreshMultipleDatabases
{
    /**
     * Define hooks to migrate the database before and after each test.
     * This method is automatically called by Laravel's TestCase::setUpTraits()
     */
    public function setUpRefreshMultipleDatabases(): void
    {
        $this->refreshDatabase();
    }

    /**
     * Define hooks to migrate the database before and after each test.
     */
    public function refreshDatabase(): void
    {
        $this->beforeRefreshingDatabase();

        $this->refreshTestDatabase();

        $this->afterRefreshingDatabase();
    }

    /**
     * Perform any work that should take place before the database has started refreshing.
     */
    protected function beforeRefreshingDatabase(): void
    {
        // ...
    }

    /**
     * Perform any work that should take place once the database has finished refreshing.
     */
    protected function afterRefreshingDatabase(): void
    {
        // ...
    }

    /**
     * Define a set of database connections to migrate.
     *
     * @return array<string, null>
     */
    protected function connectionsToMigrate(): array
    {
        $connections = [
            'sys' => null,
            'mst' => null,
        ];

        // Add dynamic sharded connections
        $shardCount = (int) env('DB_TRX_SHARDS', 2);
        
        for ($i = 1; $i <= $shardCount; $i++) {
            $connections["trx{$i}"] = null;
            $connections["log{$i}"] = null;
        }

        return $connections;
    }

    /**
     * Refresh the in-memory database.
     */
    protected function refreshInMemoryDatabase(): void
    {
        $this->artisan('migrate', $this->migrateUsing());

        // Migrate additional database connections
        foreach ($this->connectionsToMigrate() as $connection => $paths) {
            $this->artisan('migrate', [
                '--database' => $connection,
            ]);
        }

        $this->app[Kernel::class]->setArtisan(null);
    }

    /**
     * Refresh a conventional test database.
     */
    protected function refreshTestDatabase(): void
    {
        // Use absolute path to ensure same location across all test instances
        $baseDir = base_path('storage/framework/testing');
        $flagFile = $baseDir . '/.migrated';
        $lockFile = $baseDir . '/.migrate.lock';
        
        // Ensure directory exists
        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0755, true);
        }
        
        // Acquire exclusive lock
        $lock = fopen($lockFile, 'c+');
        
        if (flock($lock, LOCK_EX)) {
            try {
                // Double-check if migrations were already run by another process
                clearstatcache(true, $flagFile);
                $flagExists = file_exists($flagFile);
                
                if (! $flagExists) {
                    // Run migrations once with sys as the default connection
                    // Migrations use Schema::connection('xxx') to specify target database
                    // so they will create tables in the correct database regardless of --database option
                    $this->artisan('migrate', [
                        '--database' => 'sys',
                        '--force' => true,
                    ]);

                    // Run seeders for sys database after migrations
                    $this->artisan('db:seed', [
                        '--database' => 'sys',
                        '--class' => 'Database\\Seeders\\SysDeploySeeder',
                        '--force' => true,
                    ]);

                    $this->app[Kernel::class]->setArtisan(null);

                    // Create flag file
                    file_put_contents($flagFile, '1');
                }
            } finally {
                // Release lock
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }

        $this->beginDatabaseTransaction();
    }

    /**
     * Begin a database transaction on all connections.
     */
    public function beginDatabaseTransaction(): void
    {
        $database = $this->app->make('db');

        // Start transaction for all connections
        foreach (array_keys($this->connectionsToMigrate()) as $connectionName) {
            $connection = $database->connection($connectionName);
            $dispatcher = $connection->getEventDispatcher();
            $connection->unsetEventDispatcher();
            $connection->beginTransaction();
            $connection->setEventDispatcher($dispatcher);
        }

        $this->beforeApplicationDestroyed(function () use ($database) {
            // Rollback all connections
            foreach (array_keys($this->connectionsToMigrate()) as $connectionName) {
                $connection = $database->connection($connectionName);
                $dispatcher = $connection->getEventDispatcher();
                $connection->unsetEventDispatcher();
                $connection->rollback();
                $connection->setEventDispatcher($dispatcher);
                $connection->disconnect();
            }
        });
    }
}
