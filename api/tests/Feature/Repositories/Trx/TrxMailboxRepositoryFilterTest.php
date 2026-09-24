<?php

namespace Tests\Feature\Repositories\Trx;

use App\Domain\Mailbox\Constants\Category;
use App\Domain\Mailbox\Constants\Priority;
use App\Models\Trx\TrxMailbox;
use App\Persistence\ApiSession;
use App\Repositories\Trx\TrxMailboxRepository;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Models\_BaseModel;
use Nexus\Core\Utilities\ClockUtility;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * TrxMailboxRepository の絞り込みテスト
 *
 * メール一覧はカテゴリ・優先度・未読・保護で絞り込める。
 * 期限切れは常に除外される。
 *
 * カテゴリと優先度はマスター側の値で絞るため、
 * マスターを2種類用意して引き分けられることを確認する。
 */
class TrxMailboxRepositoryFilterTest extends TestCase
{
    use RefreshMultipleDatabases;

    private const SYSTEM_MAILBOX_ID = 'mailbox_filter_system';

    private const BATTLE_MAILBOX_ID = 'mailbox_filter_battle';

    private int $sysPlayerId;

    private string $connection;

    private TrxMailboxRepository $repository;

    public function beginDatabaseTransaction(): void
    {
        // 直接INSERTするため自動ラップしない
    }

    protected function setUp(): void
    {
        parent::setUp();

        ClockUtility::setNow('2026-03-15 12:00:00');

        $this->cleanUpMaster();
        $this->makeMailboxMaster();

        ['player' => $player] = $this->signUpPlayer();
        $this->sysPlayerId = $player->id;
        ApiSession::setSysPlayerId($this->sysPlayerId);
        $this->connection = $this->playerConnection($this->sysPlayerId);

        $this->repository = app(TrxMailboxRepository::class);
    }

    protected function tearDown(): void
    {
        DB::connection($this->connection)->table('trx_mailbox')
            ->where('sys_player_id', $this->sysPlayerId)->delete();

        $this->cleanUpMaster();
        ApiSession::clearForTest();
        ClockUtility::reset();

        parent::tearDown();
    }

    #[Test]
    public function 絞り込み無しなら全件返る(): void
    {
        $this->makeMailbox(self::SYSTEM_MAILBOX_ID);
        $this->makeMailbox(self::BATTLE_MAILBOX_ID);

        $this->assertCount(2, $this->repository->selectByPlayerId($this->sysPlayerId));
    }

    #[Test]
    public function 期限切れは常に除外される(): void
    {
        $this->makeMailbox(self::SYSTEM_MAILBOX_ID, expiresAt: '2026-03-15 11:59:59');
        $alive = $this->makeMailbox(self::BATTLE_MAILBOX_ID, expiresAt: '2026-03-15 12:00:01');

        $mailboxes = $this->repository->selectByPlayerId($this->sysPlayerId);

        $this->assertCount(1, $mailboxes);
        $this->assertSame($alive->id, $mailboxes->first()->id);
    }

    #[Test]
    public function 期限が未設定なら残る(): void
    {
        $this->makeMailbox(self::SYSTEM_MAILBOX_ID, expiresAt: null);

        $this->assertCount(1, $this->repository->selectByPlayerId($this->sysPlayerId));
    }

    #[Test]
    public function 論理削除されたメールは除外される(): void
    {
        $this->makeMailbox(self::SYSTEM_MAILBOX_ID, isDelete: true);
        $this->makeMailbox(self::BATTLE_MAILBOX_ID);

        $this->assertCount(1, $this->repository->selectByPlayerId($this->sysPlayerId));
    }

    #[Test]
    public function カテゴリで絞り込める(): void
    {
        $system = $this->makeMailbox(self::SYSTEM_MAILBOX_ID);
        $this->makeMailbox(self::BATTLE_MAILBOX_ID);

        $mailboxes = $this->repository->selectByPlayerId($this->sysPlayerId, Category::SYSTEM);

        $this->assertCount(1, $mailboxes);
        $this->assertSame($system->id, $mailboxes->first()->id);
    }

    #[Test]
    public function 優先度で絞り込める(): void
    {
        $this->makeMailbox(self::SYSTEM_MAILBOX_ID);
        $battle = $this->makeMailbox(self::BATTLE_MAILBOX_ID);

        $mailboxes = $this->repository->selectByPlayerId($this->sysPlayerId, null, Priority::URGENT);

        $this->assertCount(1, $mailboxes);
        $this->assertSame($battle->id, $mailboxes->first()->id);
    }

    #[Test]
    public function 未読だけに絞り込める(): void
    {
        $unread = $this->makeMailbox(self::SYSTEM_MAILBOX_ID);
        $this->makeMailbox(self::BATTLE_MAILBOX_ID, readAt: '2026-03-15 10:00:00');

        $mailboxes = $this->repository->selectByPlayerId($this->sysPlayerId, null, null, true);

        $this->assertCount(1, $mailboxes);
        $this->assertSame($unread->id, $mailboxes->first()->id);
    }

    #[Test]
    public function 保護されたものだけに絞り込める(): void
    {
        $this->makeMailbox(self::SYSTEM_MAILBOX_ID);
        $protected = $this->makeMailbox(self::BATTLE_MAILBOX_ID, isProtected: true);

        $mailboxes = $this->repository->selectByPlayerId($this->sysPlayerId, null, null, false, true);

        $this->assertCount(1, $mailboxes);
        $this->assertSame($protected->id, $mailboxes->first()->id);
    }

    #[Test]
    public function 絞り込みは組み合わせられる(): void
    {
        $this->makeMailbox(self::SYSTEM_MAILBOX_ID, isProtected: true);
        $this->makeMailbox(self::BATTLE_MAILBOX_ID, readAt: '2026-03-15 10:00:00', isProtected: true);

        // 保護されていて、かつ未読
        $mailboxes = $this->repository->selectByPlayerId($this->sysPlayerId, null, null, true, true);

        $this->assertCount(1, $mailboxes);
    }

    #[Test]
    public function カテゴリ別の未読数を数えられる(): void
    {
        $this->makeMailbox(self::SYSTEM_MAILBOX_ID);
        $this->makeMailbox(self::BATTLE_MAILBOX_ID);
        $this->makeMailbox(self::BATTLE_MAILBOX_ID, readAt: '2026-03-15 10:00:00');

        $counts = $this->repository->countUnreadByCategory($this->sysPlayerId);

        $this->assertSame(1, $counts[Category::SYSTEM->value] ?? 0);
        $this->assertSame(1, $counts[Category::BATTLE->value] ?? 0, '既読の1件は数えない');
    }

    #[Test]
    public function 他プレイヤーのメールは混ざらない(): void
    {
        $this->makeMailbox(self::SYSTEM_MAILBOX_ID);

        ['player' => $other] = $this->signUpPlayer();
        ApiSession::clearForTest();
        ApiSession::setSysPlayerId($other->id);

        $this->assertCount(0, app(TrxMailboxRepository::class)->selectByPlayerId($other->id));
    }

    private function makeMailbox(
        string $mstMailboxId,
        ?string $expiresAt = '2026-04-15 12:00:00',
        ?string $readAt = null,
        bool $isProtected = false,
        bool $isDelete = false,
    ): TrxMailbox {
        $connection = $this->connection;

        return _BaseModel::allowDirectWrites(function () use ($mstMailboxId, $expiresAt, $readAt, $isProtected, $isDelete, $connection) {
            $mailbox = new TrxMailbox([
                'sys_player_id' => $this->sysPlayerId,
                'mst_mailbox_id' => $mstMailboxId,
                'is_opened' => false,
                'is_received' => false,
                'is_protected' => $isProtected,
                'is_delete' => $isDelete,
                'expires_at' => $expiresAt,
                'read_at' => $readAt,
            ]);
            $mailbox->setConnection($connection);
            $mailbox->save();

            return $mailbox;
        });
    }

    private function makeMailboxMaster(): void
    {
        DB::connection('mst')->table('mst_message')->insert([
            ['id' => 'message_filter_001'],
        ]);

        DB::connection('mst')->table('mst_mailbox')->insert([
            [
                'id' => self::SYSTEM_MAILBOX_ID,
                'mst_message_id' => 'message_filter_001',
                'category' => Category::SYSTEM->value,
                'priority' => Priority::NORMAL->value,
                'sender_type' => 'System',
                'expires_in_days' => 30,
            ],
            [
                'id' => self::BATTLE_MAILBOX_ID,
                'mst_message_id' => 'message_filter_001',
                'category' => Category::BATTLE->value,
                'priority' => Priority::URGENT->value,
                'sender_type' => 'System',
                'expires_in_days' => 30,
            ],
        ]);

        $this->refreshMstCache();
    }

    private function cleanUpMaster(): void
    {
        DB::connection('mst')->table('mst_mailbox')
            ->whereIn('id', [self::SYSTEM_MAILBOX_ID, self::BATTLE_MAILBOX_ID])->delete();
        DB::connection('mst')->table('mst_message')->where('id', 'message_filter_001')->delete();
        $this->refreshMstCache();
    }
}
