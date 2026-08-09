<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * シャーディング対応データベースマイグレーションコマンド
 *
 * 設定されたすべてのトランザクションシャードに対してマイグレーションを実行します。
 */
class MigrateShards extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:shards
                            {--database= : The database connection to use}
                            {--force : Force the operation to run when in production}
                            {--path=* : The path(s) to the migrations files to be executed (default: database/migrations/trx)}
                            {--realpath : Indicate any provided migration file paths are pre-resolved absolute paths}
                            {--pretend : Dump the SQL queries that would be run}
                            {--seed : Indicates if the seed task should be re-run}
                            {--step : Force the migrations to be run so they can be rolled back individually}
                            {--fresh : Drop all tables and re-run all migrations}
                            {--refresh : Reset and re-run all migrations}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run migrations on all transaction database shards';

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

        $this->info('Running migrations on '.count($shardConnections).' shard(s): '.implode(', ', $shardConnections));
        $this->newLine();

        $hasError = false;

        foreach ($shardConnections as $connection) {
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->info("Processing shard: {$connection}");
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

            try {
                $command = $this->getMigrateCommand();
                $options = $this->buildMigrateOptions($connection);

                $exitCode = Artisan::call($command, $options, $this->getOutput());

                if ($exitCode !== 0) {
                    $this->error("Failed to migrate shard: {$connection}");
                    $hasError = true;
                } else {
                    $this->info("✅ Successfully migrated: {$connection}");
                }
            } catch (\Exception $e) {
                $this->error("Error migrating {$connection}: ".$e->getMessage());
                $hasError = true;
            }

            $this->newLine();
        }

        if ($hasError) {
            $this->error('Some migrations failed. Please check the output above.');

            return self::FAILURE;
        }

        $this->info('🎉 All shards migrated successfully!');

        return self::SUCCESS;
    }

    /**
     * 実行するマイグレーションコマンドを決定
     */
    protected function getMigrateCommand(): string
    {
        if ($this->option('fresh')) {
            return 'migrate:fresh';
        }

        if ($this->option('refresh')) {
            return 'migrate:refresh';
        }

        return 'migrate';
    }

    /**
     * マイグレーションオプションを構築
     */
    protected function buildMigrateOptions(string $connection): array
    {
        $options = [
            '--database' => $connection,
        ];

        // pathが指定されていない場合、デフォルトでtrxディレクトリを使用
        if ($this->option('path')) {
            $options['--path'] = $this->option('path');
        } else {
            $options['--path'] = ['database/migrations/trx'];
        }

        // オプションを転送
        if ($this->option('force')) {
            $options['--force'] = true;
        }

        if ($this->option('realpath')) {
            $options['--realpath'] = true;
        }

        if ($this->option('pretend')) {
            $options['--pretend'] = true;
        }

        if ($this->option('seed')) {
            $options['--seed'] = true;
        }

        if ($this->option('step')) {
            $options['--step'] = true;
        }

        return $options;
    }
}
