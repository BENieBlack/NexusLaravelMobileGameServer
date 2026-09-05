<?php

namespace Tests\Feature\Repositories\Trx;

use App\Models\Trx\TrxMailbox;
use App\Persistence\ApiSession;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Models\_BaseModel;
use NexusMailbox\Repositories\MailboxRepositoryInterface;
use NexusUnitOfWork\Persistence\QueryManager;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * MailboxRepositoryAdapter のテスト
 *
 * パッケージ層のMailboxRepositoryInterfaceの実装。
 * Model ↔ DTO の詰め替えと、状態変更がDBに反映されることを確認する。
 */
class MailboxRepositoryAdapterTest extends TestCase
{
    use RefreshMultipleDatabases;

    private int $sysPlayerId;

    private MailboxRepositoryInterface $repository;

    private QueryManager $queryManager;

    public function beginDatabaseTransaction(): void
    {
        // QueryManagerで明示的にフラッシュするため自動ラップしない
    }

    protected function setUp(): void
    {
        parent::setUp();

        ['player' => $player] = $this->signUpPlayer();
        $this->sysPlayerId = $player->id;
        ApiSession::setSysPlayerId($this->sysPlayerId);

        $this->repository = app(MailboxRepositoryInterface::class);
        $this->queryManager = app(QueryManager::class);
    }

    protected function tearDown(): void
    {
        DB::connection($this->playerConnection($this->sysPlayerId))->table('trx_mailbox')->where('sys_player_id', $this->sysPlayerId)->delete();
        ApiSession::clearForTest();
        $this->queryManager->clear();

        parent::tearDown();
    }

    #[Test]
    public function プレイヤーのメールをdtoで返す(): void
    {
        $mailbox = $this->makeMailbox();

        $mails = $this->repository->selectByPlayerId($this->sysPlayerId);

        $this->assertCount(1, $mails);
        $this->assertSame($mailbox->id, $mails->first()->getId());
        $this->assertFalse($mails->first()->isRead());
    }

    #[Test]
    public function idで引ける(): void
    {
        $mailbox = $this->makeMailbox();

        $this->assertSame($mailbox->id, $this->repository->selectById($mailbox->id)?->getId());
        $this->assertNull($this->repository->selectById(999999));
    }

    #[Test]
    public function 既読にできる(): void
    {
        $mailbox = $this->makeMailbox();

        $this->repository->markAsRead($this->repository->selectById($mailbox->id));
        $this->queryManager->execAllQuery();

        $this->assertTrue((bool) $this->findRow($mailbox->id)->is_opened);
    }

    #[Test]
    public function 受取済みにできる(): void
    {
        $mailbox = $this->makeMailbox();

        $this->repository->markDtoAsReceived($this->repository->selectById($mailbox->id));
        $this->queryManager->execAllQuery();

        $this->assertTrue((bool) $this->findRow($mailbox->id)->is_received);
    }

    #[Test]
    public function 保護状態を切り替えられる(): void
    {
        $mailbox = $this->makeMailbox();

        $this->repository->updateLockStatus($this->repository->selectById($mailbox->id), true);
        $this->queryManager->execAllQuery();

        $this->assertTrue((bool) $this->findRow($mailbox->id)->is_protected);
    }

    #[Test]
    public function dtoの状態をまとめて書き戻せる(): void
    {
        $mailbox = $this->makeMailbox();
        $dto = $this->repository->selectById($mailbox->id);

        $dto->setIsRead(true);
        $dto->setIsReceived(true);
        $dto->setIsLocked(true);
        $this->repository->persist($dto);
        $this->queryManager->execAllQuery();

        $row = $this->findRow($mailbox->id);
        $this->assertTrue((bool) $row->is_opened);
        $this->assertTrue((bool) $row->is_received);
        $this->assertTrue((bool) $row->is_protected);
    }

    #[Test]
    public function 存在しないメールへの操作は何もしない(): void
    {
        $mailbox = $this->makeMailbox();
        $dto = $this->repository->selectById($mailbox->id);

        DB::connection($this->playerConnection($this->sysPlayerId))->table('trx_mailbox')->where('id', $mailbox->id)->delete();
        app(QueryManager::class)->clear();

        // 対象が無いので例外にはならず、何も起きない
        $this->repository->markAsRead($dto);
        $this->repository->markDtoAsReceived($dto);
        $this->repository->updateLockStatus($dto, true);
        $this->repository->persist($dto);

        $this->assertNull($this->findRow($mailbox->id));
    }

    #[Test]
    public function カテゴリごとの未読件数を返す(): void
    {
        $this->makeMailbox();

        $counts = $this->repository->countUnreadByCategory($this->sysPlayerId);

        $this->assertIsArray($counts);
    }

    private function makeMailbox(): TrxMailbox
    {
        return _BaseModel::allowDirectWrites(function () {
            $mailbox = new TrxMailbox([
                'sys_player_id' => $this->sysPlayerId,
                'mst_mailbox_id' => 'mailbox_test_001',
                'is_opened' => false,
                'is_received' => false,
                'is_protected' => false,
                'expires_at' => now()->addDays(30),
            ]);
            $mailbox->setConnection($this->playerConnection($this->sysPlayerId));
            $mailbox->save();

            return $mailbox;
        });
    }

    private function findRow(int $id): ?object
    {
        return DB::connection($this->playerConnection($this->sysPlayerId))->table('trx_mailbox')->where('id', $id)->first();
    }
}
