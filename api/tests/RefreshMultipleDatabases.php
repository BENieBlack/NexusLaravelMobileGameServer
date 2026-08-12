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
     * Tracks whether migrations already ran in this PHP process.
     */
    protected static bool $migrationsRunForProcess = false;

    /**
     * Refresh a conventional test database.
     */
    protected function refreshTestDatabase(): void
    {
        $this->ensureDatabasesMigrated();

        $this->beginDatabaseTransaction();
    }

    /**
     * Make sure every test database is migrated and seeded, at most once per process.
     *
     * Feature tests that cannot be wrapped in a transaction (because the code under
     * test manages its own transactions) call this directly instead of
     * refreshTestDatabase(), and clean up their own rows in tearDown().
     */
    protected function ensureDatabasesMigrated(): void
    {
        if (! static::$migrationsRunForProcess) {
            // Use absolute path to ensure same location across all test instances
            $baseDir = base_path('storage/framework/testing');
            $flagFile = $baseDir.'/.migrated';
            $lockFile = $baseDir.'/.migrate.lock';

            // Ensure directory exists
            if (! is_dir($baseDir)) {
                mkdir($baseDir, 0755, true);
            }

            // Acquire exclusive lock
            $lock = fopen($lockFile, 'c+');

            if (flock($lock, LOCK_EX)) {
                try {
                    // The fingerprint changes whenever a migration file is added or edited,
                    // so schema changes are picked up instead of being masked by a stale flag.
                    $fingerprint = $this->migrationFingerprint();

                    clearstatcache(true, $flagFile);
                    $upToDate = file_exists($flagFile)
                        && file_get_contents($flagFile) === $fingerprint;

                    if (! $upToDate) {
                        $this->runMigrations();

                        file_put_contents($flagFile, $fingerprint);
                    }
                } finally {
                    // Release lock
                    flock($lock, LOCK_UN);
                    fclose($lock);
                }
            }

            // Seed separately from the migration guard: some feature tests run without a
            // transaction and delete sys_deploy rows in tearDown, so a process that skips
            // migrations still needs the baseline data restored.
            $this->seedSystemData();

            static::$migrationsRunForProcess = true;
        }
    }

    /**
     * Seed the baseline sys data every test relies on (deploy versions, sharding).
     */
    protected function seedSystemData(): void
    {
        $this->artisan('db:seed', [
            '--database' => 'sys',
            '--class' => 'Database\\Seeders\\SysDeploySeeder',
            '--force' => true,
        ]);

        $this->app[Kernel::class]->setArtisan(null);
    }

    /**
     * Run every migration group against the connections it belongs to.
     *
     * sys/mst migrations pin their target with Schema::connection(), but trx/log
     * migrations use the default connection. They must therefore be run once per
     * shard with --database, and with --path limited to their own directory —
     * otherwise every trx/log table lands in whichever database happens to be
     * the default for the run.
     */
    protected function runMigrations(): void
    {
        foreach ($this->migrationTargets() as [$connection, $paths]) {
            $this->artisan('migrate', [
                '--database' => $connection,
                '--path' => $paths,
                '--force' => true,
            ]);
        }

        $this->app[Kernel::class]->setArtisan(null);
    }

    /**
     * Build the [connection, migration paths] pairs to run, in dependency order.
     *
     * @return list<array{0: string, 1: list<string>}>
     */
    protected function migrationTargets(): array
    {
        $targets = [
            ['sys', $this->migrationPaths('sys')],
            ['mst', $this->migrationPaths('mst')],
        ];

        $trxPaths = $this->migrationPaths('trx');
        $logPaths = $this->migrationPaths('log');
        $shardCount = (int) env('DB_TRX_SHARDS', 2);

        for ($i = 1; $i <= $shardCount; $i++) {
            $targets[] = ["trx{$i}", $trxPaths];
            $targets[] = ["log{$i}", $logPaths];
        }

        return $targets;
    }

    /**
     * Collect every migration directory for a database group, relative to base_path().
     *
     * Discovered by globbing so a new package does not need to be registered here.
     *
     * @return list<string>
     */
    protected function migrationPaths(string $group): array
    {
        $absolute = array_merge(
            glob(base_path("database/migrations/{$group}"), GLOB_ONLYDIR) ?: [],
            glob(base_path("../packages/*/database/migrations/{$group}"), GLOB_ONLYDIR) ?: [],
        );

        $basePath = base_path().'/';

        return array_values(array_map(
            fn (string $path): string => str_starts_with($path, $basePath)
                ? substr($path, strlen($basePath))
                : $path,
            $absolute
        ));
    }

    /**
     * Hash the migration files so an added or edited migration invalidates the flag file.
     */
    protected function migrationFingerprint(): string
    {
        $entries = [];

        foreach (['sys', 'mst', 'trx', 'log'] as $group) {
            foreach ($this->migrationPaths($group) as $path) {
                foreach (glob(base_path($path).'/*.php') ?: [] as $file) {
                    $entries[] = $file.':'.filemtime($file).':'.filesize($file);
                }
            }
        }

        sort($entries);

        return md5(implode('|', $entries).'|shards='.env('DB_TRX_SHARDS', 2));
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
