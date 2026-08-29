<?php

namespace NexusPitr\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use NexusPitr\Logger\ShardMapper;
use NexusPitr\Support\ShardMigrationPaths;

/**
 * TrxMigrateCommand
 * 
 * すべてのTrxDBシャードに対してマイグレーションを実行
 * 動的シャーディング対応（DB_SHARD_COUNTに応じてtrx1, trx2, ...に実行）
 */
class TrxMigrateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trx:migrate 
                            {--fresh : Drop all tables on each shard and re-run every migration}
                            {--force : Force the operation to run when in production}
                            {--seed : Indicates if the seed task should be re-run}
                            {--step : Force the migrations to be run so they can be rolled back individually}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run TrxDB migrations on all shards dynamically';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $trxConnections = ShardMapper::allTrxConnections();
        
        // パスはpackages/を走査して集める（パッケージを増やしても直さなくてよい）
        $trxMigrationPaths = ShardMigrationPaths::find('trx');
        
        $shardCount = count($trxConnections);
        $shardList = implode(', ', $trxConnections);
        
        $this->info("Running TrxDB migrations on all {$shardCount} shards...");
        $this->info("Target shards: {$shardList}");
        $this->info('Migration paths: '.implode(', ', $trxMigrationPaths));
        $this->newLine();
        
        foreach ($trxConnections as $trxConnection) {
            $this->info("📦 Migrating TrxDB: {$trxConnection}");
            
            $options = [
                '--database' => $trxConnection,
                '--path' => $trxMigrationPaths,
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
            
            // TrxDBマイグレーション実行（trxサブディレクトリのみ）
            // --fresh はシャードの全テーブルを落としてから流し直す。
            // 既存マイグレーションを書き換えたときはこちらでないと反映されない
            $migrateCommand = $this->option('fresh') ? 'migrate:fresh' : 'migrate';

            $exitCode = Artisan::call($migrateCommand, $options, $this->getOutput());
            
            if ($exitCode !== 0) {
                $this->error("❌ Migration failed for {$trxConnection}");
                return self::FAILURE;
            }
            
            $this->info("✅ Migration completed for {$trxConnection}");
            $this->newLine();
        }
        
        $this->info('🎉 All TrxDB migrations completed successfully!');
        
        return self::SUCCESS;
    }
}
