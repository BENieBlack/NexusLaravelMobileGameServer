<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class DockerTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'docker:test 
                            {--fresh : Run migrations before testing}
                            {--filter= : Filter tests to run}
                            {--testsuite= : Specify test suite (Unit, Feature)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run tests in Docker environment';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting Docker environment...');

        // Start Docker containers
        $this->runProcess(['docker-compose', 'up', '-d', 'db-sys', 'db-mst', 'db-trx1', 'db-trx2', 'db-log1', 'db-log2', 'redis']);

        $this->info('Waiting for databases to be ready...');
        sleep(5);

        if ($this->option('fresh')) {
            $this->info('Running fresh migrations...');
            $this->runMigrations();
        }

        $this->info('Running tests...');
        $testCommand = ['docker-compose', 'exec', '-T', 'api-php', 'php', 'artisan', 'test'];

        if ($filter = $this->option('filter')) {
            $testCommand[] = '--filter='.$filter;
        }

        if ($testsuite = $this->option('testsuite')) {
            $testCommand[] = '--testsuite='.$testsuite;
        }

        return $this->runProcess($testCommand, true);
    }

    /**
     * Run migrations in Docker environment
     */
    private function runMigrations(): void
    {
        $databases = [
            ['connection' => 'sys', 'path' => 'database/migrations/sys'],
            ['connection' => 'mst', 'path' => 'database/migrations/mst'],
            ['connection' => 'trx', 'path' => 'database/migrations/trx'],
            ['connection' => 'log', 'path' => 'database/migrations/log'],
        ];

        foreach ($databases as $db) {
            $this->info("Migrating {$db['connection']}...");
            $this->runProcess([
                'docker-compose', 'exec', '-T', 'api-php', 'php', 'artisan', 'migrate:fresh',
                '--database='.$db['connection'],
                '--path='.$db['path'],
                '--force',
            ]);
        }

        // Run seeder
        $this->info('Running seeders...');
        $this->runProcess([
            'docker-compose', 'exec', '-T', 'api-php', 'php', 'artisan', 'db:seed',
            '--database=sys',
            '--class=Database\\Seeders\\SysDeploySeeder',
            '--force',
        ]);
    }

    /**
     * Run a process and display output
     */
    private function runProcess(array $command, bool $returnExitCode = false): int
    {
        $process = new Process($command, base_path('..'));
        $process->setTimeout(null);
        $process->setTty(Process::isTtySupported());

        $exitCode = $process->run(function ($type, $buffer) {
            echo $buffer;
        });

        if ($returnExitCode) {
            return $exitCode;
        }

        if (! $process->isSuccessful()) {
            $this->error('Command failed: '.implode(' ', $command));
        }

        return $exitCode;
    }
}
