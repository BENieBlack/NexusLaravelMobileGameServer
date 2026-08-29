<?php

namespace Tests\Feature\Mailbox;

use App\Domain\Mailbox\UseCases\ReceiveAllUseCase;
use App\Domain\Mailbox\UseCases\ReceiveUseCase;
use App\Models\Trx\TrxMailbox;
use App\Persistence\ApiSession;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Models\_BaseModel;
use NexusUnitOfWork\Persistence\QueryManager;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * メール添付物の受取のテスト
 *
 * メールの中身は mst_mailbox_content で、種別は Diamond / PaidDiamond /
 * AlliancePoints のようなパスカルケースで入る。これを配送側の
 * ResourceType（スネークケース）へ正しく写せているかが要点。
 *
 * 配布量も content_quantity × amount で、片方だけ見ると数が合わない。
 */
class MailboxReceiveContentTest extends TestCase
{
    use RefreshMultipleDatabases;

    private const MST_MAILBOX_ID = 'mailbox_content_test';

    private int $sysPlayerId;

    private QueryManager $queryManager;

    public function beginDatabaseTransaction(): void
    {
        // UseCaseが自前でトランザクションを張るため自動ラップしない
    }

    protected function setUp(): void
    {
        parent::setUp();

        ['player' => $player] = $this->signUpPlayer();
        $this->sysPlayerId = $player->id;
        ApiSession::setSysPlayerId($this->sysPlayerId);

        $this->queryManager = app(QueryManager::class);
        $this->cleanUpMaster();
        $this->makeMstMailbox();
    }

    protected function tearDown(): void
    {
        DB::connection($this->playerConnection($this->sysPlayerId))
            ->table('trx_mailbox')->where('sys_player_id', $this->sysPlayerId)->delete();
        $this->cleanUpMaster();
        ApiSession::clearForTest();
        $this->queryManager->clear();

        parent::tearDown();
    }

    #[Test]
    public function 一語の種別を受け取れる(): void
    {
        $this->makeContent('Gold', 'gold', contentQuantity: 1, amount: 500);
        $mailbox = $this->makeMailbox();

        app(ReceiveUseCase::class)->exec($this->sysPlayerId, $mailbox->id);
        $this->flush();

        $this->assertSame(500, $this->findWalletAmount('gold'));
    }

    #[Test]
    public function 複数語の種別も受け取れる(): void
    {
        // PaidDiamond を素で小文字化すると paiddiamond になり、
        // ResourceType の paid_diamond と合わずに ValueError で落ちる
        $this->makeContent('AlliancePoints', 'alliance_points', contentQuantity: 1, amount: 30);
        $mailbox = $this->makeMailbox();

        app(ReceiveUseCase::class)->exec($this->sysPlayerId, $mailbox->id);
        $this->flush();

        $this->assertSame(30, $this->findWalletAmount('alliance_points'));
    }

    #[Test]
    public function 配布量はcontent_quantityとamountの積になる(): void
    {
        // 「1回あたり10個 × 3回配布」で30個
        $this->makeContent('Gold', 'gold', contentQuantity: 10, amount: 3);
        $mailbox = $this->makeMailbox();

        app(ReceiveUseCase::class)->exec($this->sysPlayerId, $mailbox->id);
        $this->flush();

        $this->assertSame(30, $this->findWalletAmount('gold'));
    }

    #[Test]
    public function 一括受取でも複数語の種別を受け取れる(): void
    {
        $this->makeContent('AlliancePoints', 'alliance_points', contentQuantity: 1, amount: 30);
        $this->makeMailbox();

        app(ReceiveAllUseCase::class)->exec($this->sysPlayerId);
        $this->flush();

        $this->assertSame(30, $this->findWalletAmount('alliance_points'));
    }

    #[Test]
    public function 一括受取でも配布量は積になる(): void
    {
        $this->makeContent('Gold', 'gold', contentQuantity: 10, amount: 3);
        $this->makeMailbox();

        app(ReceiveAllUseCase::class)->exec($this->sysPlayerId);
        $this->flush();

        $this->assertSame(30, $this->findWalletAmount('gold'));
    }

    #[Test]
    public function 一括受取は複数のメールをまとめて受け取る(): void
    {
        $this->makeContent('Gold', 'gold', contentQuantity: 1, amount: 100);
        $first = $this->makeMailbox();
        $second = $this->makeMailbox();

        $response = app(ReceiveAllUseCase::class)->exec($this->sysPlayerId)->toArray();
        $this->flush();

        $this->assertSame(2, $response['total_count']);
        $this->assertSame(0, $response['skipped_count']);
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $response['received_mailbox_ids']);
        $this->assertSame(200, $this->findWalletAmount('gold'), '2通ぶん合算される');

        foreach ([$first, $second] as $mailbox) {
            $this->assertTrue((bool) $this->findMailbox($mailbox->id)->is_received);
        }
    }

    #[Test]
    public function 受取済みのメールは指定しても数に入らない(): void
    {
        $this->makeContent('Gold', 'gold', contentQuantity: 1, amount: 100);
        $received = $this->makeMailbox(isReceived: true);
        $fresh = $this->makeMailbox();

        $response = app(ReceiveAllUseCase::class)->exec($this->sysPlayerId, [$received->id, $fresh->id])->toArray();
        $this->flush();

        $this->assertSame(1, $response['total_count']);
        $this->assertSame(1, $response['skipped_count']);
        $this->assertSame([$fresh->id], $response['received_mailbox_ids']);
        $this->assertSame(100, $this->findWalletAmount('gold'), '二重には配らない');
    }

    #[Test]
    public function 他人のメールは指定しても受け取れない(): void
    {
        $this->makeContent('Gold', 'gold', contentQuantity: 1, amount: 100);
        $mine = $this->makeMailbox();

        ['player' => $other] = $this->signUpPlayer();
        $othersMailbox = $this->makeMailbox(sysPlayerId: $other->id);
        ApiSession::setSysPlayerId($this->sysPlayerId);

        $response = app(ReceiveAllUseCase::class)->exec($this->sysPlayerId, [$mine->id, $othersMailbox->id])->toArray();
        $this->flush();

        $this->assertSame([$mine->id], $response['received_mailbox_ids']);
        $this->assertFalse((bool) $this->findMailbox($othersMailbox->id, $other->id)->is_received);
    }

    #[Test]
    public function 添付物が無いメールも受取済みになる(): void
    {
        $mailbox = $this->makeMailbox();

        $response = app(ReceiveAllUseCase::class)->exec($this->sysPlayerId)->toArray();
        $this->flush();

        $this->assertSame(1, $response['total_count']);
        $this->assertArrayNotHasKey('delivery_contents', $response);
        $this->assertTrue((bool) $this->findMailbox($mailbox->id)->is_received);
    }

    private function flush(): void
    {
        $this->queryManager->execAllQuery();
        $this->queryManager->clear();
    }

    private function findWalletAmount(string $mstItemId): int
    {
        $row = DB::connection($this->playerConnection($this->sysPlayerId))
            ->table('trx_wallet')
            ->where('sys_player_id', $this->sysPlayerId)
            ->where('mst_item_id', $mstItemId)
            ->first();

        return $row ? (int) $row->free_amount + (int) $row->paid_amount : 0;
    }

    private function findMailbox(int $id, ?int $sysPlayerId = null): object
    {
        $row = DB::connection($this->playerConnection($sysPlayerId ?? $this->sysPlayerId))
            ->table('trx_mailbox')->where('id', $id)->first();

        $this->assertNotNull($row);

        return $row;
    }

    private function makeMailbox(bool $isReceived = false, ?int $sysPlayerId = null): TrxMailbox
    {
        $sysPlayerId ??= $this->sysPlayerId;
        $connection = $this->playerConnection($sysPlayerId);

        return _BaseModel::allowDirectWrites(function () use ($sysPlayerId, $isReceived, $connection) {
            $mailbox = new TrxMailbox([
                'sys_player_id' => $sysPlayerId,
                'mst_mailbox_id' => self::MST_MAILBOX_ID,
                'is_opened' => false,
                'is_received' => $isReceived,
                'is_delete' => false,
                'is_protected' => false,
                'expires_at' => now()->addDays(30),
            ]);
            $mailbox->setConnection($connection);
            $mailbox->save();

            return $mailbox;
        });
    }

    private function makeMstMailbox(): void
    {
        DB::connection('mst')->table('mst_mailbox')->insert([
            'id' => self::MST_MAILBOX_ID,
            'mst_message_id' => 'message_test_001',
            'category' => 'System',
            'priority' => 'Normal',
            'sender_type' => 'System',
            'expires_in_days' => 30,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->refreshMstCache();
    }

    private function makeContent(string $contentType, string $contentMstId, int $contentQuantity, int $amount): void
    {
        DB::connection('mst')->table('mst_mailbox_content')->insert([
            'mst_mailbox_id' => self::MST_MAILBOX_ID,
            'content_type' => $contentType,
            'content_mst_id' => $contentMstId,
            'content_quantity' => $contentQuantity,
            'amount' => $amount,
            'is_highlight' => false,
            'sort_desc' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->refreshMstCache();
    }

    private function cleanUpMaster(): void
    {
        DB::connection('mst')->table('mst_mailbox_content')
            ->where('mst_mailbox_id', self::MST_MAILBOX_ID)->delete();
        DB::connection('mst')->table('mst_mailbox')->where('id', self::MST_MAILBOX_ID)->delete();
        $this->refreshMstCache();
    }
}
