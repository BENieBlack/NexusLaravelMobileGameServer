<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabaseState;

trait RefreshMultipleDatabases
{
    /**
     * Define hooks to migrate the database before and after each test.
     * This method is automatically called by Laravel's TestCase::setUpTraits()
     *
     * @return void
     */
    public function setUpRefreshMultipleDatabases(): void
    {
        $this->refreshDatabase();
    }

    /**
     * Define hooks to migrate the database before and after each test.
     *
     * @return void
     */
    public function refreshDatabase(): void
    {
        $this->beforeRefreshingDatabase();

        $this->refreshTestDatabase();

        $this->afterRefreshingDatabase();
    }

    /**
     * Perform any work that should take place before the database has started refreshing.
     *
     * @return void
     */
    protected function beforeRefreshingDatabase(): void
    {
        // ...
    }

    /**
     * Perform any work that should take place once the database has finished refreshing.
     *
     * @return void
     */
    protected function afterRefreshingDatabase(): void
    {
        // ...
    }

    /**
     * Define a set of database connections and their migration paths.
     *
     * @return array<string, string>
     */
    protected function connectionsToMigrate(): array
    {
        return [
            'sys' => 'database/migrations/sys',
            'mst' => 'database/migrations/mst',
            'trx' => 'database/migrations/trx',
            'log' => 'database/migrations/log',
        ];
    }

    /**
     * Refresh the in-memory database.
     *
     * @return void
     */
    protected function refreshInMemoryDatabase(): void
    {
        $this->artisan('migrate', $this->migrateUsing());

        // Migrate additional database connections
        foreach ($this->connectionsToMigrate() as $connection => $path) {
            $this->artisan('migrate', [
                '--database' => $connection,
                '--path' => $path,
            ]);
        }

        $this->app[Kernel::class]->setArtisan(null);
    }

    /**
     * Refresh a conventional test database.
     *
     * @return void
     */
    protected function refreshTestDatabase(): void
    {
        if (! RefreshDatabaseState::$migrated) {
            // Run migrate:fresh for each connection with its specific path
            foreach ($this->connectionsToMigrate() as $connection => $path) {
                // First, drop all tables in the database
                $this->artisan('migrate:reset', [
                    '--database' => $connection,
                    '--path' => $path,
                    '--force' => true,
                ]);
                
                // Then run fresh migrations
                $this->artisan('migrate', [
                    '--database' => $connection,
                    '--path' => $path,
                    '--force' => true,
                ]);
            }

            $this->app[Kernel::class]->setArtisan(null);

            RefreshDatabaseState::$migrated = true;
        }

        $this->beginDatabaseTransaction();
    }

    /**
     * Begin a database transaction on all connections.
     *
     * @return void
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
