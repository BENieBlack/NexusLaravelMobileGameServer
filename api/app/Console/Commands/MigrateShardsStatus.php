<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * シャーディング対応マイグレーションステータス確認コマンド
 */
class MigrateShardsStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:shards-status
                            {--database= : The database connection to use (if specified, only this shard will be checked)}
                            {--pending : Only list pending migrations}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show the status of each migration on all transaction database shards';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // 特定のデータベースが指定されている場合
        if ($database = $this->option('database')) {
            return $this->showStatusForConnection($database);
        }

        // すべてのシャードのステータスを表示
        $shardConnections = array_values(config('sharding.transaction.nodes', []));

        if (empty($shardConnections)) {
            $this->error('No shard connections configured in config/sharding.php');

            return self::FAILURE;
        }

        $this->info('Checking migration status for '.count($shardConnections).' shard(s)');
        $this->newLine();

        foreach ($shardConnections as $connection) {
            $this->showStatusForConnection($connection);
            $this->newLine();
        }

        return self::SUCCESS;
    }

    /**
     * 指定された接続のマイグレーションステータスを表示
     */
    protected function showStatusForConnection(string $connection): int
    {
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info("Migration status for: {$connection}");
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $options = [
            '--database' => $connection,
        ];

        if ($this->option('pending')) {
            $options['--pending'] = true;
        }

        return Artisan::call('migrate:status', $options, $this->getOutput());
    }
}
