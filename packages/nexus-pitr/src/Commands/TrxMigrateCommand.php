<?php

namespace NexusPitr\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use NexusPitr\Logger\ShardMapper;

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
        
        // TrxDBマイグレーションパスを定義（base_path()からの相対パス）
        $trxMigrationPaths = [
            '../packages/nexus-core/database/migrations/trx',
            '../packages/nexus-resource/database/migrations/trx',
            '../packages/nexus-wallet/database/migrations/trx',
            '../packages/nexus-stamina/database/migrations/trx',
            '../packages/nexus-core-billing/database/migrations/trx',
            '../packages/nexus-mailbox/database/migrations/trx',
            '../packages/nexus-gacha/database/migrations/trx',
            '../packages/nexus-login/database/migrations/trx',
            '../packages/nexus-vip/database/migrations/trx',
            '../packages/nexus-album/database/migrations/trx',
            // TiDB用の変換は、対象テーブルが揃ったあとに流す
            '../packages/nexus-tidb/database/migrations/trx',
        ];
        
        $shardCount = count($trxConnections);
        $shardList = implode(', ', $trxConnections);
        
        $this->info("Running TrxDB migrations on all {$shardCount} shards...");
        $this->info("Target shards: {$shardList}");
        $this->info('This includes migrations from packages: nexus-core, nexus-resource, nexus-wallet, nexus-stamina, nexus-core-billing, nexus-mailbox, nexus-gacha, nexus-login, nexus-vip, nexus-album, nexus-tidb');
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
            $exitCode = Artisan::call('migrate', $options, $this->getOutput());
            
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
