<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use NexusPitr\Support\ShardMigrationPaths;

/**
 * シャーディング対応マイグレーションロールバックコマンド
 */
class MigrateShardsRollback extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:shards-rollback
                            {--database= : The database connection to use}
                            {--force : Force the operation to run when in production}
                            {--path=* : The path(s) to the migrations files to be executed (default: 全パッケージのtrxマイグレーション)}
                            {--realpath : Indicate any provided migration file paths are pre-resolved absolute paths}
                            {--pretend : Dump the SQL queries that would be run}
                            {--step=1 : The number of migrations to be reverted}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rollback the last database migration on all transaction database shards';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // シャード接続名を設定から取得
        $shardConnections = array_values(config('sharding.transaction.nodes', []));

        if (empty($shardConnections)) {
            $this->error('No shard connections configured in config/sharding.php');

            return self::FAILURE;
        }

        $this->warn('Rolling back migrations on '.count($shardConnections).' shard(s): '.implode(', ', $shardConnections));
        $this->newLine();

        if (! $this->option('force') && ! $this->confirm('Do you really wish to rollback migrations on all shards?')) {
            $this->info('Rollback cancelled.');

            return self::SUCCESS;
        }

        $hasError = false;

        foreach ($shardConnections as $connection) {
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->info("Rolling back shard: {$connection}");
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

            try {
                $options = $this->buildRollbackOptions($connection);
                $exitCode = Artisan::call('migrate:rollback', $options, $this->getOutput());

                if ($exitCode !== 0) {
                    $this->error("Failed to rollback shard: {$connection}");
                    $hasError = true;
                } else {
                    $this->info("✅ Successfully rolled back: {$connection}");
                }
            } catch (\Exception $e) {
                $this->error("Error rolling back {$connection}: ".$e->getMessage());
                $hasError = true;
            }

            $this->newLine();
        }

        if ($hasError) {
            $this->error('Some rollbacks failed. Please check the output above.');

            return self::FAILURE;
        }

        $this->info('🎉 All shards rolled back successfully!');

        return self::SUCCESS;
    }

    /**
     * ロールバックオプションを構築
     */
    protected function buildRollbackOptions(string $connection): array
    {
        $options = [
            '--database' => $connection,
        ];

        // pathが指定されていない場合、デフォルトでtrxディレクトリを使用
        if ($this->option('path')) {
            $options['--path'] = $this->option('path');
        } else {
            // パスはpackages/を走査して集める（パッケージのtrxマイグレーションも流す）
            $options['--path'] = ShardMigrationPaths::find('trx');
        }

        if ($this->option('force')) {
            $options['--force'] = true;
        }

        if ($this->option('realpath')) {
            $options['--realpath'] = true;
        }

        if ($this->option('pretend')) {
            $options['--pretend'] = true;
        }

        if ($this->option('step')) {
            $options['--step'] = $this->option('step');
        }

        return $options;
    }
}
