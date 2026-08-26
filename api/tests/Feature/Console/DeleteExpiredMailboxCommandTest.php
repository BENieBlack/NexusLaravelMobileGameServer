<?php

namespace Tests\Feature\Console;

use App\Models\Trx\TrxMailbox;
use App\Persistence\ApiSession;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Models\_BaseModel;
use Nexus\Core\Utilities\ClockUtility;
use NexusUnitOfWork\Persistence\QueryManager;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * mailbox:delete-expired のテスト
 *
 * 期限切れメールを論理削除するバッチ。
 * 保護されたメールと削除済みのメールを触らないことが要点。
 */
class DeleteExpiredMailboxCommandTest extends TestCase
{
    use RefreshMultipleDatabases;

    private int $sysPlayerId;

    private int $otherPlayerId;

    public function beginDatabaseTransaction(): void
    {
        // コマンドがQueryManagerでフラッシュするため自動ラップしない
    }

    protected function setUp(): void
    {
        parent::setUp();

        ClockUtility::setNow('2026-03-15 12:00:00');

        ['player' => $player] = $this->signUpPlayer();
        $this->sysPlayerId = $player->id;

        ['player' => $other] = $this->signUpPlayer();
        $this->otherPlayerId = $other->id;

        ApiSession::setSysPlayerId($this->sysPlayerId);
        app(QueryManager::class)->clear();
    }

    protected function tearDown(): void
    {
        DB::connection('trx1')->table('trx_mailbox')
            ->whereIn('sys_player_id', [$this->sysPlayerId, $this->otherPlayerId])->delete();
        ApiSession::clearForTest();
        ClockUtility::reset();
        app(QueryManager::class)->clear();

        parent::tearDown();
    }

    #[Test]
    public function 期限切れのメールを論理削除する(): void
    {
        $expired = $this->makeMailbox(expiresAt: '2026-03-15 11:59:59');
        $alive = $this->makeMailbox(expiresAt: '2026-03-15 12:00:01');

        $this->artisan('mailbox:delete-expired')
            ->expectsOutputToContain('削除: 1 件')
            ->assertExitCode(0);

        $this->assertTrue((bool) $this->findRow($expired->id)->is_delete);
        $this->assertFalse((bool) $this->findRow($alive->id)->is_delete);
    }

    #[Test]
    public function dry_runでは削除しない(): void
    {
        $expired = $this->makeMailbox(expiresAt: '2026-03-15 11:00:00');

        $this->artisan('mailbox:delete-expired', ['--dry-run' => true])
            ->expectsOutputToContain('[DRY RUN モード]')
            ->expectsOutputToContain('削除: 1 件')
            ->assertExitCode(0);

        $this->assertFalse((bool) $this->findRow($expired->id)->is_delete, 'dry-runではDBを変更しない');
    }

    #[Test]
    public function 保護されたメールは対象にしない(): void
    {
        $protected = $this->makeMailbox(expiresAt: '2026-03-15 11:00:00', isProtected: true);

        $this->artisan('mailbox:delete-expired')
            ->expectsOutputToContain('期限切れメールは見つかりませんでした')
            ->assertExitCode(0);

        $this->assertFalse((bool) $this->findRow($protected->id)->is_delete);
    }

    #[Test]
    public function 期限が未設定のメールは対象にしない(): void
    {
        $noExpiry = $this->makeMailbox(expiresAt: null);

        $this->artisan('mailbox:delete-expired')->assertExitCode(0);

        $this->assertFalse((bool) $this->findRow($noExpiry->id)->is_delete);
    }

    #[Test]
    public function プレイヤーを指定するとそのプレイヤーだけ処理する(): void
    {
        $mine = $this->makeMailbox(expiresAt: '2026-03-15 11:00:00');
        $others = $this->makeMailbox(expiresAt: '2026-03-15 11:00:00', sysPlayerId: $this->otherPlayerId);

        $this->artisan('mailbox:delete-expired', ['--player-id' => $this->sysPlayerId])
            ->expectsOutputToContain("対象プレイヤー: {$this->sysPlayerId}")
            ->assertExitCode(0);

        $this->assertTrue((bool) $this->findRow($mine->id)->is_delete);
        $this->assertFalse((bool) $this->findRow($others->id)->is_delete);
    }

    #[Test]
    public function 件数の上限を指定できる(): void
    {
        $this->makeMailbox(expiresAt: '2026-03-15 11:00:00');
        $this->makeMailbox(expiresAt: '2026-03-15 11:00:00');

        $this->artisan('mailbox:delete-expired', ['--limit' => 1])
            ->expectsOutputToContain('期限切れメールが 1 件見つかりました')
            ->assertExitCode(0);

        $deleted = DB::connection('trx1')->table('trx_mailbox')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('is_delete', true)
            ->count();

        $this->assertSame(1, $deleted);
    }

    #[Test]
    public function 対象が無ければ何もせず終わる(): void
    {
        $this->artisan('mailbox:delete-expired')
            ->expectsOutputToContain('期限切れメールは見つかりませんでした')
            ->assertExitCode(0);
    }

    private function makeMailbox(?string $expiresAt, bool $isProtected = false, ?int $sysPlayerId = null): TrxMailbox
    {
        $sysPlayerId ??= $this->sysPlayerId;

        return _BaseModel::allowDirectWrites(function () use ($expiresAt, $isProtected, $sysPlayerId) {
            $mailbox = new TrxMailbox([
                'sys_player_id' => $sysPlayerId,
                'mst_mailbox_id' => 'mailbox_test_001',
                'is_opened' => false,
                'is_received' => false,
                'is_protected' => $isProtected,
                'expires_at' => $expiresAt,
            ]);
            $mailbox->setConnection('trx1');
            $mailbox->save();

            return $mailbox;
        });
    }

    private function findRow(int $id): ?object
    {
        return DB::connection('trx1')->table('trx_mailbox')->where('id', $id)->first();
    }
}
