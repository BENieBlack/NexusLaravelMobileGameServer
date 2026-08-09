<?php

namespace NexusPitr\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use NexusPitr\Logger\ShardMapper;

/**
 * TrxMigrateCommand
 * 
 * すべてのTrxDBシャードに対してマイグレーションを実行
 * 動的シャーディング対応（DB_TRX_SHARDSに応じてtrx1, trx2, ...に実行）
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
        $trxConnections = ShardMapper::getAllTrxConnections();
        
        $this->info('Running TrxDB migrations on all shards...');
        $this->info('This includes migrations from: api/database/migrations/trx');
        $this->newLine();
        
        foreach ($trxConnections as $trxConnection) {
            $this->info("📦 Migrating TrxDB: {$trxConnection}");
            
            $options = [
                '--database' => $trxConnection,
                '--path' => 'database/migrations/trx',
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
