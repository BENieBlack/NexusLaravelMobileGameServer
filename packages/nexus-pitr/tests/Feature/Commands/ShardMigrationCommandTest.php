<?php

namespace NexusPitr\Tests\Feature\Commands;

use Illuminate\Support\Facades\Artisan;
use NexusPitr\Commands\PitrMigrateCommand;
use NexusPitr\Commands\PitrRollbackCommand;
use NexusPitr\Commands\TrxMigrateCommand;
use NexusPitr\Commands\TrxRollbackCommand;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

/**
 * trx:migrate / trx:rollback / pitr:migrate / pitr:rollback のテスト
 *
 * 実際にマイグレーションを流すとテスト用DBを壊すため、
 * 内部で呼ぶ Artisan::call を差し替えて、どのシャードへ
 * どのオプションを渡しているかを検証する。
 *
 * シャード数は DB_TRX_SHARDS で決まる。テストは2シャード前提
 * （phpunit.xml と CI の両方で 2 に揃えてある）。
 */
class ShardMigrationCommandTest extends TestCase
{
    /** @var list<array{0: string, 1: array<string, mixed>}> Artisan::call の呼び出し記録 */
    private array $calls = [];

    private int $exitCodeToReturn = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calls = [];
        $this->exitCodeToReturn = 0;

        Artisan::shouldReceive('call')->andReturnUsing(function (string $command, array $options = []) {
            $this->calls[] = [$command, $options];

            return $this->exitCodeToReturn;
        });
    }

    #[Test]
    public function trx_migrateは全trxシャードにマイグレーションを流す(): void
    {
        $output = new BufferedOutput;

        $this->assertSame(TrxMigrateCommand::SUCCESS, $this->runCommand(TrxMigrateCommand::class, [], $output));

        $this->assertSame(['trx1', 'trx2'], array_column(array_column($this->calls, 1), '--database'));
        $this->assertSame(['migrate', 'migrate'], array_column($this->calls, 0));

        // trxサブディレクトリだけを対象にする
        foreach ($this->calls[0][1]['--path'] as $path) {
            $this->assertStringEndsWith('/database/migrations/trx', $path);
        }

        $this->assertStringContainsString('Target shards: trx1, trx2', $output->fetch());
    }

    #[Test]
    public function trx_migrateはオプションを転送する(): void
    {
        $this->runCommand(TrxMigrateCommand::class, ['--force' => true, '--seed' => true, '--step' => true]);

        $options = $this->calls[0][1];
        $this->assertTrue($options['--force']);
        $this->assertTrue($options['--seed']);
        $this->assertTrue($options['--step']);
    }

    #[Test]
    public function trx_migrateは失敗したシャードで打ち切る(): void
    {
        $this->exitCodeToReturn = 1;
        $output = new BufferedOutput;

        $this->assertSame(TrxMigrateCommand::FAILURE, $this->runCommand(TrxMigrateCommand::class, [], $output));

        // 1つ目で失敗したら後続のシャードには進まない
        $this->assertCount(1, $this->calls);
        $this->assertStringContainsString('Migration failed for trx1', $output->fetch());
    }

    #[Test]
    public function trx_rollbackは段数を指定して全シャードを戻す(): void
    {
        $this->assertSame(
            TrxRollbackCommand::SUCCESS,
            $this->runCommand(TrxRollbackCommand::class, ['--step' => 2, '--force' => true])
        );

        $this->assertSame(['migrate:rollback', 'migrate:rollback'], array_column($this->calls, 0));
        $this->assertSame(['trx1', 'trx2'], array_column(array_column($this->calls, 1), '--database'));
        $this->assertSame(2, $this->calls[0][1]['--step']);
        $this->assertTrue($this->calls[0][1]['--force']);
    }

    #[Test]
    public function pitr_migrateは全logシャードにマイグレーションを流す(): void
    {
        $output = new BufferedOutput;

        $this->assertSame(PitrMigrateCommand::SUCCESS, $this->runCommand(PitrMigrateCommand::class, [], $output));

        $this->assertSame(['log1', 'log2'], array_column(array_column($this->calls, 1), '--database'));

        // logサブディレクトリだけを対象にする
        foreach ($this->calls[0][1]['--path'] as $path) {
            $this->assertStringEndsWith('database/migrations/log', $path);
        }

        $this->assertStringContainsString('Target shards: log1, log2', $output->fetch());
    }

    #[Test]
    public function pitr_rollbackはlogのパスを指定して全シャードを戻す(): void
    {
        $this->assertSame(
            PitrRollbackCommand::SUCCESS,
            $this->runCommand(PitrRollbackCommand::class, ['--force' => true])
        );

        $this->assertSame(['log1', 'log2'], array_column(array_column($this->calls, 1), '--database'));
        $this->assertSame('database/migrations/log', $this->calls[0][1]['--path']);
    }

    #[Test]
    public function pitr_rollbackも失敗したシャードで打ち切る(): void
    {
        $this->exitCodeToReturn = 1;
        $output = new BufferedOutput;

        $this->assertSame(PitrRollbackCommand::FAILURE, $this->runCommand(PitrRollbackCommand::class, [], $output));

        $this->assertCount(1, $this->calls);
        $this->assertStringContainsString('Rollback failed for log1', $output->fetch());
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function runCommand(string $commandClass, array $options = [], ?BufferedOutput $output = null): int
    {
        $command = $this->app->make($commandClass);
        $command->setLaravel($this->app);

        $input = new ArrayInput($options);
        $input->setInteractive(false);

        return $command->run($input, $output ?? new BufferedOutput);
    }
}
