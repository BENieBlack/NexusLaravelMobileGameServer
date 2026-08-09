<?php

namespace NexusPitr\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use NexusPitr\Logger\ShardMapper;

/**
 * PitrMigrateCommand
 * 
 * すべてのLogDBシャードに対してマイグレーションを実行
 * 動的シャーディング対応（DB_TRX_SHARDSに応じてlog1, log2, ...に実行）
 */
class PitrMigrateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pitr:migrate 
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
        $logConnections = ShardMapper::getAllLogConnections();
        
        // LogDBマイグレーションパスを定義
        $logMigrationPaths = [
            'packages/nexus-pitr/database/migrations/log',
            'packages/nexus-player/database/migrations/log',
            'packages/nexus-resource/database/migrations/log',
            'packages/nexus-wallet/database/migrations/log',
            'packages/nexus-stamina/database/migrations/log',
            'packages/nexus-core-billing/database/migrations/log',
            'packages/nexus-mailbox/database/migrations/log',
            'packages/nexus-gacha/database/migrations/log',
            'packages/nexus-login/database/migrations/log',
            'packages/nexus-vip/database/migrations/log',
        ];
        
        $this->info('Running LogDB migrations on all shards...');
        $this->info('This includes migrations from: nexus-pitr, nexus-player, nexus-resource, nexus-wallet, nexus-stamina, nexus-core-billing, nexus-mailbox, nexus-gacha, nexus-login, nexus-vip');
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
            $exitCode = Artisan::call('migrate', $options, $this->getOutput());
            
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
