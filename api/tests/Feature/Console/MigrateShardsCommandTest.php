<?php

namespace Tests\Feature\Console;

use App\Console\Commands\MigrateShards;
use App\Console\Commands\MigrateShardsRollback;
use App\Console\Commands\MigrateShardsStatus;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

/**
 * migrate:shards / migrate:shards-rollback のテスト
 *
 * 実際にマイグレーションを流すとテスト用DBを壊すため、
 * 内部で呼ぶ Artisan::call を差し替えて、どのシャードへ
 * どのオプションを渡しているかを検証する。
 */
class MigrateShardsCommandTest extends TestCase
{
    /** @var list<array{0: string, 1: array<string, mixed>}> Artisan::call の呼び出し記録 */
    private array $calls = [];

    private int $exitCodeToReturn = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calls = [];
        $this->exitCodeToReturn = 0;

        Config::set('sharding.transaction.nodes', [1 => 'trx1', 2 => 'trx2']);
    }

    #[Test]
    public function 全シャードにマイグレーションを流す(): void
    {
        $this->fakeArtisan();

        $this->assertSame(MigrateShards::SUCCESS, $this->runCommand(MigrateShards::class));

        $this->assertSame(['migrate', 'migrate'], array_column($this->calls, 0));
        $this->assertSame('trx1', $this->calls[0][1]['--database']);
        $this->assertSame('trx2', $this->calls[1][1]['--database']);

        // パス未指定ならtrxディレクトリを既定にする
        $this->assertSame(['database/migrations/trx'], $this->calls[0][1]['--path']);
    }

    #[Test]
    public function オプションはそのまま転送する(): void
    {
        $this->fakeArtisan();

        $this->runCommand(MigrateShards::class, [
            '--path' => ['database/migrations/custom'],
            '--force' => true,
            '--pretend' => true,
            '--step' => true,
        ]);

        $options = $this->calls[0][1];
        $this->assertSame(['database/migrations/custom'], $options['--path']);
        $this->assertTrue($options['--force']);
        $this->assertTrue($options['--pretend']);
        $this->assertTrue($options['--step']);
    }

    #[Test]
    public function freshとrefreshは呼ぶコマンドが変わる(): void
    {
        $this->fakeArtisan();
        $this->runCommand(MigrateShards::class, ['--fresh' => true]);
        $this->assertSame('migrate:fresh', $this->calls[0][0]);

        $this->calls = [];
        $this->runCommand(MigrateShards::class, ['--refresh' => true]);
        $this->assertSame('migrate:refresh', $this->calls[0][0]);
    }

    #[Test]
    public function いずれかが失敗すれば失敗として返す(): void
    {
        $this->fakeArtisan();
        $this->exitCodeToReturn = 1;

        $output = new BufferedOutput;

        $this->assertSame(MigrateShards::FAILURE, $this->runCommand(MigrateShards::class, [], $output));
        $this->assertStringContainsString('Failed to migrate shard: trx1', $output->fetch());
    }

    #[Test]
    public function シャードが設定されていなければ失敗する(): void
    {
        Config::set('sharding.transaction.nodes', []);
        $output = new BufferedOutput;

        $this->assertSame(MigrateShards::FAILURE, $this->runCommand(MigrateShards::class, [], $output));
        $this->assertStringContainsString('No shard connections configured', $output->fetch());
    }

    #[Test]
    public function ロールバックは確認に答えないと実行しない(): void
    {
        $this->fakeArtisan();

        // 非対話なので確認は既定の「いいえ」になる
        $output = new BufferedOutput;
        $exitCode = $this->runCommand(MigrateShardsRollback::class, [], $output);

        $this->assertSame(MigrateShardsRollback::SUCCESS, $exitCode);
        $this->assertStringContainsString('Rollback cancelled.', $output->fetch());
        $this->assertSame([], $this->calls, '確認を拒否したらロールバックは走らない');
    }

    #[Test]
    public function forceを付ければ確認なしで全シャードをロールバックする(): void
    {
        $this->fakeArtisan();

        $exitCode = $this->runCommand(MigrateShardsRollback::class, ['--force' => true]);

        $this->assertSame(MigrateShardsRollback::SUCCESS, $exitCode);
        $this->assertSame(['migrate:rollback', 'migrate:rollback'], array_column($this->calls, 0));
        $this->assertSame('trx1', $this->calls[0][1]['--database']);
        $this->assertSame('trx2', $this->calls[1][1]['--database']);
    }

    #[Test]
    public function ステータスは全シャード分を表示する(): void
    {
        $this->fakeArtisan();
        $output = new BufferedOutput;

        $this->assertSame(MigrateShardsStatus::SUCCESS, $this->runCommand(MigrateShardsStatus::class, [], $output));

        $text = $output->fetch();
        $this->assertStringContainsString('Checking migration status for 2 shard(s)', $text);
        $this->assertStringContainsString('Migration status for: trx1', $text);
        $this->assertStringContainsString('Migration status for: trx2', $text);

        $this->assertSame(['migrate:status', 'migrate:status'], array_column($this->calls, 0));
    }

    #[Test]
    public function ステータスは接続を指定すればそのシャードだけ見る(): void
    {
        $this->fakeArtisan();
        $output = new BufferedOutput;

        $this->runCommand(MigrateShardsStatus::class, ['--database' => 'trx2', '--pending' => true], $output);

        $this->assertStringNotContainsString('Migration status for: trx1', $output->fetch());
        $this->assertCount(1, $this->calls);
        $this->assertSame('trx2', $this->calls[0][1]['--database']);
        $this->assertTrue($this->calls[0][1]['--pending']);
    }

    #[Test]
    public function ステータスもシャード未設定なら失敗する(): void
    {
        Config::set('sharding.transaction.nodes', []);
        $output = new BufferedOutput;

        $this->assertSame(MigrateShardsStatus::FAILURE, $this->runCommand(MigrateShardsStatus::class, [], $output));
        $this->assertStringContainsString('No shard connections configured', $output->fetch());
    }

    /**
     * Artisan::call を記録するだけの実装に差し替える
     *
     * この差し替えの後は $this->artisan() が使えないため、
     * コマンドは直接組み立てて実行する。
     */
    private function fakeArtisan(): void
    {
        Artisan::shouldReceive('call')->andReturnUsing(function (string $command, array $options = []) {
            $this->calls[] = [$command, $options];

            return $this->exitCodeToReturn;
        });
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function runCommand(
        string $commandClass,
        array $options = [],
        ?BufferedOutput $output = null,
        ?ArrayInput $input = null
    ): int {
        $command = $this->app->make($commandClass);
        $command->setLaravel($this->app);

        $input ??= new ArrayInput($options);
        // 確認プロンプトで止まらないよう非対話で走らせる（既定値=いいえ が使われる）
        $input->setInteractive(false);

        return $command->run($input, $output ?? new BufferedOutput);
    }
}
