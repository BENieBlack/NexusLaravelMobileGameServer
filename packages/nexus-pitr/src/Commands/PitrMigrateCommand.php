<?php

namespace NexusPitr\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use NexusPitr\Logger\ShardMapper;
use NexusPitr\Support\ShardMigrationPaths;

/**
 * PitrMigrateCommand
 * 
 * すべてのLogDBシャードに対してマイグレーションを実行
 * 動的シャーディング対応（DB_SHARD_COUNTに応じてlog1, log2, ...に実行）
 */
class PitrMigrateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pitr:migrate 
                            {--fresh : Drop all tables on each shard and re-run every migration}
                            {--force : Force the operation to run when in production}
                            {--seed : Indicates if the seed task should be re-run}
                            {--step : Force the migrations to be run so they can be rolled back individually}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run PITR migrations on all LogDB shards dynamically';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $logConnections = ShardMapper::allLogConnections();
        
        // LogDBマイグレーションパスを定義（base_path()からの相対パス）
        // パスはpackages/を走査して集める（パッケージを増やしても直さなくてよい）
        $logMigrationPaths = ShardMigrationPaths::find('log');
        
        $shardCount = count($logConnections);
        $shardList = implode(', ', $logConnections);
        
        $this->info("Running LogDB migrations on all {$shardCount} shards...");
        $this->info("Target shards: {$shardList}");
        $this->info('Migration paths: '.implode(', ', $logMigrationPaths));
        $this->newLine();
        
        foreach ($logConnections as $logConnection) {
            $this->info("📦 Migrating LogDB: {$logConnection}");
            
            $options = [
                '--database' => $logConnection,
                '--path' => $logMigrationPaths,
            ];
            
            if ($this->option('force')) {
                $options['--force'] = true;
            }
            
            if ($this->option('seed')) {
                $options['--seed'] = true;
            }
            
            if ($this->option('step')) {
                $options['--step'] = true;
            }
            
            // LogDBマイグレーション実行（logサブディレクトリのみ）
            // --fresh はシャードの全テーブルを落としてから流し直す。
            // 既存マイグレーションを書き換えたときはこちらでないと反映されない
            $migrateCommand = $this->option('fresh') ? 'migrate:fresh' : 'migrate';

            $exitCode = Artisan::call($migrateCommand, $options, $this->getOutput());
            
            if ($exitCode !== 0) {
                $this->error("❌ Migration failed for {$logConnection}");
                return self::FAILURE;
            }
            
            $this->info("✅ Migration completed for {$logConnection}");
            $this->newLine();
        }
        
        $this->info('🎉 All LogDB migrations completed successfully!');
        
        return self::SUCCESS;
    }
}
