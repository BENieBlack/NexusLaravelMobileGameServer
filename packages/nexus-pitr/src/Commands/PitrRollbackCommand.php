<?php

namespace NexusPitr\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use NexusPitr\Logger\ShardMapper;
use NexusPitr\Support\ShardMigrationPaths;

/**
 * PitrRollbackCommand
 * 
 * すべてのLogDBシャードに対してマイグレーションロールバックを実行
 * 動的シャーディング対応（DB_SHARD_COUNTに応じてlog1, log2, ...に実行）
 */
class PitrRollbackCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pitr:rollback 
                            {--force : Force the operation to run when in production}
                            {--step=1 : The number of migrations to be reverted}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rollback PITR migrations on all LogDB shards dynamically';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $logConnections = ShardMapper::allLogConnections();
        
        $this->info('Rolling back PITR migrations on all LogDB shards...');
        $this->newLine();
        
        foreach ($logConnections as $logConnection) {
            $this->info("📦 Rolling back LogDB: {$logConnection}");
            
            $options = [
                '--database' => $logConnection,
                '--path' => ShardMigrationPaths::find('log'),
                '--step' => $this->option('step'),
            ];
            
            if ($this->option('force')) {
                $options['--force'] = true;
            }
            
            $exitCode = Artisan::call('migrate:rollback', $options, $this->getOutput());
            
            if ($exitCode !== 0) {
                $this->error("❌ Rollback failed for {$logConnection}");
                return self::FAILURE;
            }
            
            $this->info("✅ Rollback completed for {$logConnection}");
            $this->newLine();
        }
        
        $this->info('🎉 All PITR rollbacks completed successfully!');
        
        return self::SUCCESS;
    }
}
