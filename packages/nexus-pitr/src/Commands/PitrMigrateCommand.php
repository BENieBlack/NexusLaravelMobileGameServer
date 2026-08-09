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
        
        $this->info('Running PITR migrations on all LogDB shards...');
        $this->newLine();
        
        foreach ($logConnections as $logConnection) {
            $this->info("📦 Migrating LogDB: {$logConnection}");
            
            $options = [
                '--database' => $logConnection,
                '--path' => 'database/migrations/log',
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
                $this->error("❌ Migration failed for {$logConnection}");
                return self::FAILURE;
            }
            
            $this->info("✅ Migration completed for {$logConnection}");
            $this->newLine();
        }
        
        $this->info('🎉 All PITR migrations completed successfully!');
        
        return self::SUCCESS;
    }
}
