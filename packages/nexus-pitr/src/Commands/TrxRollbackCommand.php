<?php

namespace NexusPitr\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use NexusPitr\Logger\ShardMapper;

/**
 * TrxRollbackCommand
 * 
 * すべてのTrxDBシャードに対してマイグレーションをロールバック
 * 動的シャーディング対応（DB_TRX_SHARDSに応じてtrx1, trx2, ...に実行）
 */
class TrxRollbackCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trx:rollback 
                            {--force : Force the operation to run when in production}
                            {--step=1 : Number of migrations to rollback}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rollback TrxDB migrations on all shards dynamically';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $trxConnections = ShardMapper::allTrxConnections();
        
        $this->info('Rolling back TrxDB migrations on all shards...');
        $this->info('This includes migrations from packages: nexus-core-player, nexus-resource, nexus-wallet, nexus-stamina, nexus-core-billing, nexus-mailbox, nexus-gacha, nexus-login, nexus-vip');
        $this->newLine();
        
        foreach ($trxConnections as $trxConnection) {
            $this->info("📦 Rolling back TrxDB: {$trxConnection}");
            
            $options = [
                '--database' => $trxConnection,
                '--step' => $this->option('step'),
            ];
            
            if ($this->option('force')) {
                $options['--force'] = true;
            }
            
            $exitCode = Artisan::call('migrate:rollback', $options, $this->getOutput());
            
            if ($exitCode !== 0) {
                $this->error("❌ Rollback failed for {$trxConnection}");
                return self::FAILURE;
            }
            
            $this->info("✅ Rollback completed for {$trxConnection}");
            $this->newLine();
        }
        
        $this->info('🎉 All TrxDB rollbacks completed successfully!');
        
        return self::SUCCESS;
    }
}
