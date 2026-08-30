<?php

namespace Tests\Feature\Repositories\Sys;

use App\Models\Sys\SysFriendApply;
use App\Persistence\ApiSession;
use App\Repositories\Sys\SysFriendApplyRepository;
use Illuminate\Support\Facades\DB;
use NexusUnitOfWork\Persistence\QueryManager;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * SysFriendApplyRepository のテスト
 *
 * sys_friend_apply は「送信者か受信者のどちらかが自分」で自分の行になる。
 * 自分が絡む行はキャッシュ経由で読み書きし、赤の他人同士の申請は
 * キャッシュにも更新キューにも載せない。
 *
 * 向きを問わない検索（AがBに出した申請とBがAに出した申請は同じもの）が要点。
 */
class SysFriendApplyRepositoryTest extends TestCase
{
    use RefreshMultipleDatabases;

    private int $me = 9001;

    private int $friend = 9002;

    private int $stranger = 9003;

    private int $otherStranger = 9004;

    private SysFriendApplyRepository $repository;

    private QueryManager $queryManager;

    public function beginDatabaseTransaction(): void
    {
        // QueryManagerで明示的にフラッシュするため自動ラップしない
    }

    protected function setUp(): void
    {
        parent::setUp();

        ApiSession::clearForTest();
        ApiSession::setSysPlayerId($this->me);

        $this->repository = app(SysFriendApplyRepository::class);
        $this->queryManager = app(QueryManager::class);

        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        ApiSession::clearForTest();
        $this->queryManager->clear();

        parent::tearDown();
    }

    // ========================================
    // 向きを問わない検索
    // ========================================

    #[Test]
    public function 自分が出した申請を引ける(): void
    {
        $id = $this->makeApply($this->me, $this->friend);

        $apply = $this->repository->selectByPlayerPair($this->me, $this->friend);

        $this->assertNotNull($apply);
        $this->assertSame($id, $apply->getId());
    }

    #[Test]
    public function 引数の順が逆でも同じ申請を引ける(): void
    {
        // AがBに出した申請は、BがAを調べたときにも見つからないといけない
        $id = $this->makeApply($this->friend, $this->me);

        $this->assertSame($id, $this->repository->selectByPlayerPair($this->me, $this->friend)?->getId());
        $this->assertSame($id, $this->repository->selectByPlayerPair($this->friend, $this->me)?->getId());
    }

    #[Test]
    public function 承認済みの関係も引ける(): void
    {
        $this->makeApply($this->me, $this->friend, SysFriendApply::STATUS_ACCEPTED);

        $this->assertNotNull($this->repository->selectByPlayerPair($this->me, $this->friend));
    }

    #[Test]
    public function 却下済みや削除済みは引けない(): void
    {
        // 再申請できるようにするため、決着済みは「存在しない」扱い
        foreach ([SysFriendApply::STATUS_REJECTED, SysFriendApply::STATUS_DELETED] as $status) {
            $this->cleanUp();
            $this->makeApply($this->me, $this->friend, $status);

            $this->assertNull(
                $this->repository->selectByPlayerPair($this->me, $this->friend),
                "{$status} が引けてしまった"
            );
        }
    }

    #[Test]
    public function 他人同士の申請も引けるが更新はできない(): void
    {
        // 自分が絡まないのでキャッシュを通さない読み取りになる
        $id = $this->makeApply($this->stranger, $this->otherStranger);

        $apply = $this->repository->selectByPlayerPair($this->stranger, $this->otherStranger);

        $this->assertSame($id, $apply?->getId());
        $this->assertCount(0, $this->repository->queryOrMemory(), '他人の行はキャッシュに載らない');
    }

    #[Test]
    public function 他人同士でも決着済みは引けない(): void
    {
        // キャッシュを通さない経路。向きのORを括らないと
        // (片方向) OR (もう片方向 AND ステータス) になり、
        // 片方向だけステータスが無視される
        $this->makeApply($this->stranger, $this->otherStranger, SysFriendApply::STATUS_REJECTED);

        $this->assertNull($this->repository->selectByPlayerPair($this->stranger, $this->otherStranger));
        $this->assertNull($this->repository->selectByPlayerPair($this->otherStranger, $this->stranger));
    }

    #[Test]
    public function 削除は両方向とも承認済みに限る(): void
    {
        // 自分が出した申請中のものを消せてしまうと、申請を握り潰せる
        foreach ([[$this->me, $this->friend], [$this->friend, $this->me]] as [$sender, $receiver]) {
            $this->cleanUp();
            $id = $this->makeApply($sender, $receiver);

            $this->assertNull($this->repository->deleteFriendRelationModel($this->me, $this->friend));
            $this->assertDatabaseHas('sys_friend_apply', ['id' => $id], 'sys');
        }
    }

    #[Test]
    public function 関係が無ければnull(): void
    {
        $this->assertNull($this->repository->selectByPlayerPair($this->me, $this->friend));
    }

    // ========================================
    // 一覧
    // ========================================

    #[Test]
    public function 受信した申請だけを新しい順で返す(): void
    {
        $this->makeApply($this->friend, $this->me, at: '2026-03-14 12:00:00');
        $newest = $this->makeApply($this->stranger, $this->me, at: '2026-03-15 12:00:00');
        $this->makeApply($this->me, $this->friend);

        $applies = $this->repository->selectReceivedApplies($this->me);

        $this->assertCount(2, $applies, '自分が出した分は入らない');
        $this->assertSame($newest, $applies->first()->getId());
    }

    #[Test]
    public function 送信した申請だけを返す(): void
    {
        $sent = $this->makeApply($this->me, $this->friend);
        $this->makeApply($this->stranger, $this->me);

        $applies = $this->repository->selectSentApplies($this->me);

        $this->assertCount(1, $applies);
        $this->assertSame($sent, $applies->first()->getId());
    }

    #[Test]
    public function 申請一覧は送受信の両方を含む(): void
    {
        $this->makeApply($this->me, $this->friend);
        $this->makeApply($this->stranger, $this->me);
        $this->makeApply($this->stranger, $this->otherStranger);

        $this->assertCount(2, $this->repository->selectAppliesByPlayerId($this->me), '他人同士の分は入らない');
    }

    #[Test]
    public function 申請一覧に決着済みは入らない(): void
    {
        $this->makeApply($this->me, $this->friend, SysFriendApply::STATUS_ACCEPTED);
        $this->makeApply($this->stranger, $this->me, SysFriendApply::STATUS_REJECTED);

        $this->assertCount(0, $this->repository->selectAppliesByPlayerId($this->me));
    }

    #[Test]
    public function フレンド一覧は承認済みだけを返す(): void
    {
        $accepted = $this->makeApply($this->me, $this->friend, SysFriendApply::STATUS_ACCEPTED);
        $this->makeApply($this->stranger, $this->me);

        $friends = $this->repository->selectAcceptedFriendsByPlayerId($this->me);

        $this->assertCount(1, $friends);
        $this->assertSame($accepted, $friends->first()->getId());
    }

    // ========================================
    // 作成と削除
    // ========================================

    #[Test]
    public function 申請を作れる(): void
    {
        $apply = $this->repository->insertApply($this->me, $this->friend);
        $this->queryManager->execAllQuery();

        $this->assertSame(SysFriendApply::STATUS_APPLIED, $apply->getStatus());
        $this->assertDatabaseHas('sys_friend_apply', [
            'sender_sys_player_id' => $this->me,
            'receiver_sys_player_id' => $this->friend,
            'status' => SysFriendApply::STATUS_APPLIED,
        ], 'sys');
    }

    #[Test]
    public function 作った申請は同じリクエスト内で読み戻せる(): void
    {
        // フラッシュ前でもキューに積んだ行が見える
        $this->repository->insertApply($this->me, $this->friend);

        $this->assertNotNull($this->repository->selectByPlayerPair($this->me, $this->friend));
    }

    #[Test]
    public function フレンド関係を削除できる(): void
    {
        $id = $this->makeApply($this->me, $this->friend, SysFriendApply::STATUS_ACCEPTED);

        $deleted = $this->repository->deleteFriendRelationModel($this->me, $this->friend);
        $this->queryManager->execAllQuery();

        $this->assertSame($id, $deleted?->getId());
        $this->assertDatabaseMissing('sys_friend_apply', ['id' => $id], 'sys');
    }

    #[Test]
    public function 削除は向きを問わない(): void
    {
        $id = $this->makeApply($this->friend, $this->me, SysFriendApply::STATUS_ACCEPTED);

        $this->repository->deleteFriendRelationModel($this->me, $this->friend);
        $this->queryManager->execAllQuery();

        $this->assertDatabaseMissing('sys_friend_apply', ['id' => $id], 'sys');
    }

    #[Test]
    public function 承認済みでなければ削除しない(): void
    {
        // 申請中のものを「フレンド削除」で消せてしまうと申請が握り潰せる
        $id = $this->makeApply($this->me, $this->friend);

        $this->assertNull($this->repository->deleteFriendRelationModel($this->me, $this->friend));
        $this->assertDatabaseHas('sys_friend_apply', ['id' => $id], 'sys');
    }

    #[Test]
    public function 関係が無ければ削除はnullを返す(): void
    {
        $this->assertNull($this->repository->deleteFriendRelationModel($this->me, $this->friend));
    }

    // ========================================
    // 自分スコープ
    // ========================================

    #[Test]
    public function キャッシュに載るのは自分が絡む行だけ(): void
    {
        $mine = $this->makeApply($this->me, $this->friend);
        $received = $this->makeApply($this->stranger, $this->me);
        $this->makeApply($this->stranger, $this->otherStranger);

        $cached = $this->repository->queryOrMemory();

        $this->assertEqualsCanonicalizing(
            [$mine, $received],
            $cached->map(fn (SysFriendApply $apply) => $apply->getId())->values()->all(),
            '送信・受信のどちらでも自分の行'
        );
    }

    #[Test]
    public function idで引くのも自分の行に限る(): void
    {
        $mine = $this->makeApply($this->me, $this->friend);
        $others = $this->makeApply($this->stranger, $this->otherStranger);

        $this->assertSame($mine, $this->repository->selectById($mine)?->getId());

        // 他人の行も読めるが、キャッシュには入らない
        $this->assertSame($others, $this->repository->selectById($others)?->getId());
        $this->assertCount(1, $this->repository->queryOrMemory());
    }

    private function makeApply(
        int $sender,
        int $receiver,
        string $status = SysFriendApply::STATUS_APPLIED,
        ?string $at = null,
    ): int {
        return DB::connection('sys')->table('sys_friend_apply')->insertGetId([
            'sender_sys_player_id' => $sender,
            'receiver_sys_player_id' => $receiver,
            'status' => $status,
            'created_at' => $at ?? now(),
            'updated_at' => $at ?? now(),
        ]);
    }

    private function cleanUp(): void
    {
        DB::connection('sys')->table('sys_friend_apply')
            ->whereIn('sender_sys_player_id', [$this->me, $this->friend, $this->stranger, $this->otherStranger])
            ->orWhereIn('receiver_sys_player_id', [$this->me, $this->friend, $this->stranger, $this->otherStranger])
            ->delete();
        $this->queryManager->clear();
    }
}
