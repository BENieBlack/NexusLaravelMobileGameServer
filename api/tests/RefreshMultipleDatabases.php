<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabaseState;

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
     * Define a set of database connections and their migration paths.
     *
     * @return array<string, string|array>
     */
    protected function connectionsToMigrate(): array
    {
        $connections = [
            'sys' => array_merge(
                ['database/migrations/sys'],
                $this->collectPackageMigrationPaths('sys')
            ),
            'mst' => array_merge(
                ['database/migrations/mst'],
                $this->collectPackageMigrationPaths('mst')
            ),
        ];

        // Add dynamic sharded connections with their migration paths
        $shardCount = (int) env('DB_TRX_SHARDS', 2);
        
        // Collect all package migration paths for trx and log
        $trxPaths = $this->collectPackageMigrationPaths('trx');
        $logPaths = $this->collectPackageMigrationPaths('log');
        
        for ($i = 1; $i <= $shardCount; $i++) {
            $connections["trx{$i}"] = $trxPaths;
            $connections["log{$i}"] = $logPaths;
        }

        return $connections;
    }

    /**
     * Collect migration paths from all packages for a specific database type.
     *
     * @param string $type Database type (trx, log, mst, sys, etc.)
     * @return array<string>
     */
    protected function collectPackageMigrationPaths(string $type): array
    {
        // packages directory is mounted at /var/www/packages in Docker
        // But Laravel's migrate command expects paths relative to base_path()
        // base_path() is /var/www/html, so we use ../packages
        $basePath = base_path('../packages');
        $paths = [];
        
        if (!is_dir($basePath)) {
            return $paths;
        }
        
        $packages = scandir($basePath);
        foreach ($packages as $package) {
            if ($package === '.' || $package === '..') {
                continue;
            }
            
            $migrationPath = "{$basePath}/{$package}/database/migrations/{$type}";
            if (is_dir($migrationPath)) {
                // Add relative path from base_path()
                $paths[] = "../packages/{$package}/database/migrations/{$type}";
            }
        }
        
        return $paths;
    }

    /**
     * Refresh the in-memory database.
     */
    protected function refreshInMemoryDatabase(): void
    {
        $this->artisan('migrate', $this->migrateUsing());

        // Migrate additional database connections
        foreach ($this->connectionsToMigrate() as $connection => $paths) {
            // Handle both single path (string) and multiple paths (array)
            $pathList = is_array($paths) ? $paths : [$paths];
            
            foreach ($pathList as $path) {
                $this->artisan('migrate', [
                    '--database' => $connection,
                    '--path' => $path,
                ]);
            }
        }

        $this->app[Kernel::class]->setArtisan(null);
    }

    /**
     * Refresh a conventional test database.
     */
    protected function refreshTestDatabase(): void
    {
        if (! RefreshDatabaseState::$migrated) {
            // Run migrations for each connection
            foreach ($this->connectionsToMigrate() as $connection => $paths) {
                // Handle both single path (string) and multiple paths (array)
                $pathList = is_array($paths) ? $paths : [$paths];
                
                foreach ($pathList as $path) {
                    $this->artisan('migrate', [
                        '--database' => $connection,
                        '--path' => $path,
                        '--force' => true,
                    ]);
                }
            }

            // Run seeders for sys database after migrations
            $this->artisan('db:seed', [
                '--database' => 'sys',
                '--class' => 'Database\\Seeders\\SysDeploySeeder',
                '--force' => true,
            ]);

            $this->app[Kernel::class]->setArtisan(null);

            RefreshDatabaseState::$migrated = true;
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
