<?php

namespace NexusFriend\Tests\Unit\Services;

use NexusFriend\Constants\FriendStatus;
use NexusFriend\DataTransferObjects\FriendApply;
use NexusFriend\Repositories\FriendApplyRepositoryInterface;
use NexusFriend\Services\FriendService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * FriendService のユニットテスト
 *
 * 永続化はFriendApplyRepositoryInterfaceの向こう側なので、
 * メモリ上の実装を差し込んで検証する。
 *
 * 申請・承認・却下は「誰が」「どの状態のものに」対して行えるかが要点。
 */
class FriendServiceTest extends TestCase
{
    private const SENDER = 1;

    private const RECEIVER = 2;

    #[Test]
    public function 申請を送れる(): void
    {
        $repository = new FakeFriendApplyRepository;
        $service = new FriendService($repository);

        $apply = $service->sendApply(self::SENDER, self::RECEIVER);

        $this->assertSame(self::SENDER, $apply->getSenderPlayerId());
        $this->assertSame(self::RECEIVER, $apply->getReceiverPlayerId());
        $this->assertSame(FriendStatus::APPLIED, $apply->getStatus());
        $this->assertSame([[self::SENDER, self::RECEIVER]], $repository->inserted);
    }

    #[Test]
    public function 自分自身には申請できない(): void
    {
        $service = new FriendService(new FakeFriendApplyRepository);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot send friend request to yourself');

        $service->sendApply(self::SENDER, self::SENDER);
    }

    #[Test]
    public function 申請中の相手には重ねて申請できない(): void
    {
        $repository = new FakeFriendApplyRepository;
        $repository->byPair = $this->makeApply(status: FriendStatus::APPLIED);
        $service = new FriendService($repository);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Friend request already exists');

        $service->sendApply(self::SENDER, self::RECEIVER);
    }

    #[Test]
    public function 既にフレンドの相手には申請できない(): void
    {
        $repository = new FakeFriendApplyRepository;
        $repository->byPair = $this->makeApply(status: FriendStatus::ACCEPTED);
        $service = new FriendService($repository);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Already friends');

        $service->sendApply(self::SENDER, self::RECEIVER);
    }

    #[Test]
    public function 却下済みの相手には再申請できる(): void
    {
        $repository = new FakeFriendApplyRepository;
        $repository->byPair = $this->makeApply(status: FriendStatus::REJECTED);
        $service = new FriendService($repository);

        $apply = $service->sendApply(self::SENDER, self::RECEIVER);

        $this->assertSame(FriendStatus::APPLIED, $apply->getStatus());
    }

    #[Test]
    public function 受信者は申請を承認できる(): void
    {
        $repository = new FakeFriendApplyRepository;
        $repository->byId = $this->makeApply();
        $service = new FriendService($repository);

        $accepted = $service->acceptApply(10, self::RECEIVER);

        $this->assertSame(FriendStatus::ACCEPTED, $accepted->getStatus());
    }

    #[Test]
    public function 受信者以外は承認できない(): void
    {
        $repository = new FakeFriendApplyRepository;
        $repository->byId = $this->makeApply();
        $service = new FriendService($repository);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Not authorized to accept/reject this request');

        // 送信者自身が承認しようとする
        $service->acceptApply(10, self::SENDER);
    }

    #[Test]
    public function 存在しない申請は承認できない(): void
    {
        $service = new FriendService(new FakeFriendApplyRepository);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Friend apply not found');

        $service->acceptApply(999, self::RECEIVER);
    }

    #[Test]
    public function 決着済みの申請は承認できない(): void
    {
        $messages = [
            FriendStatus::ACCEPTED => 'Friend request already accepted',
            FriendStatus::REJECTED => 'Friend request already rejected',
            FriendStatus::DELETED => 'Friend request already deleted',
        ];

        foreach ($messages as $status => $message) {
            $repository = new FakeFriendApplyRepository;
            $repository->byId = $this->makeApply(status: $status);
            $service = new FriendService($repository);

            try {
                $service->acceptApply(10, self::RECEIVER);
                $this->fail("{$status} なのに承認できてしまった");
            } catch (\RuntimeException $e) {
                $this->assertSame($message, $e->getMessage());
            }
        }
    }

    #[Test]
    public function 受信者は申請を却下できる(): void
    {
        $repository = new FakeFriendApplyRepository;
        $repository->byId = $this->makeApply();
        $service = new FriendService($repository);

        $rejected = $service->rejectApply(10, self::RECEIVER);

        $this->assertSame(FriendStatus::REJECTED, $rejected->getStatus());
    }

    #[Test]
    public function 決着済みの申請は却下できない(): void
    {
        $repository = new FakeFriendApplyRepository;
        $repository->byId = $this->makeApply(status: FriendStatus::ACCEPTED);
        $service = new FriendService($repository);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Friend request already accepted');

        $service->rejectApply(10, self::RECEIVER);
    }

    #[Test]
    public function 存在しない申請は却下できない(): void
    {
        $service = new FriendService(new FakeFriendApplyRepository);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Friend apply not found');

        $service->rejectApply(999, self::RECEIVER);
    }

    #[Test]
    public function 申請一覧とフレンド一覧を取得できる(): void
    {
        $repository = new FakeFriendApplyRepository;
        $repository->applies = [$this->makeApply()];
        $repository->friends = [$this->makeApply(status: FriendStatus::ACCEPTED)];
        $service = new FriendService($repository);

        $this->assertCount(1, $service->findApplyList(self::RECEIVER));
        $this->assertSame(FriendStatus::ACCEPTED, $service->findFriendList(self::RECEIVER)[0]->getStatus());
    }

    #[Test]
    public function フレンドを削除できる(): void
    {
        $repository = new FakeFriendApplyRepository;
        $repository->deletedRelation = $this->makeApply(status: FriendStatus::DELETED);
        $service = new FriendService($repository);

        $deleted = $service->deleteFriend(self::SENDER, self::RECEIVER);

        $this->assertSame(FriendStatus::DELETED, $deleted->getStatus());
        $this->assertSame([[self::SENDER, self::RECEIVER]], $repository->deleteCalls);
    }

    #[Test]
    public function 自分自身は削除できない(): void
    {
        $service = new FriendService(new FakeFriendApplyRepository);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot send friend request to yourself');

        $service->deleteFriend(self::SENDER, self::SENDER);
    }

    #[Test]
    public function フレンド関係が無ければ削除は失敗する(): void
    {
        $service = new FriendService(new FakeFriendApplyRepository);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Friend not found');

        $service->deleteFriend(self::SENDER, self::RECEIVER);
    }

    private function makeApply(string $status = FriendStatus::APPLIED): FriendApply
    {
        return new FriendApply(
            id: 10,
            senderPlayerId: self::SENDER,
            receiverPlayerId: self::RECEIVER,
            status: $status,
            createdAt: '2026-03-15 12:00:00',
            updatedAt: '2026-03-15 12:00:00',
        );
    }
}

/**
 * メモリ上で完結するFriendApplyRepositoryInterface実装
 */
class FakeFriendApplyRepository implements FriendApplyRepositoryInterface
{
    public ?FriendApply $byId = null;

    public ?FriendApply $byPair = null;

    public ?FriendApply $deletedRelation = null;

    /** @var array<FriendApply> */
    public array $applies = [];

    /** @var array<FriendApply> */
    public array $friends = [];

    /** @var list<array{0: int, 1: int}> */
    public array $inserted = [];

    /** @var list<array{0: int, 1: int}> */
    public array $deleteCalls = [];

    public function selectById(int $friendApplyId): ?FriendApply
    {
        return $this->byId;
    }

    public function selectByPlayerPair(int $senderPlayerId, int $receiverPlayerId): ?FriendApply
    {
        return $this->byPair;
    }

    public function selectAppliesByPlayerId(int $playerId): array
    {
        return $this->applies;
    }

    public function selectAcceptedFriendsByPlayerId(int $playerId): array
    {
        return $this->friends;
    }

    public function insert(int $senderPlayerId, int $receiverPlayerId): FriendApply
    {
        $this->inserted[] = [$senderPlayerId, $receiverPlayerId];

        return new FriendApply(
            id: 100,
            senderPlayerId: $senderPlayerId,
            receiverPlayerId: $receiverPlayerId,
            status: FriendStatus::APPLIED,
            createdAt: '2026-03-15 12:00:00',
            updatedAt: '2026-03-15 12:00:00',
        );
    }

    public function accept(FriendApply $friendApply): FriendApply
    {
        return $this->withStatus($friendApply, FriendStatus::ACCEPTED);
    }

    public function reject(FriendApply $friendApply): FriendApply
    {
        return $this->withStatus($friendApply, FriendStatus::REJECTED);
    }

    /**
     * DTOはreadonlyなので、状態だけ差し替えた新しいインスタンスを返す
     */
    private function withStatus(FriendApply $friendApply, string $status): FriendApply
    {
        return new FriendApply(
            id: $friendApply->getId(),
            senderPlayerId: $friendApply->getSenderPlayerId(),
            receiverPlayerId: $friendApply->getReceiverPlayerId(),
            status: $status,
            createdAt: $friendApply->getCreatedAt(),
            updatedAt: '2026-03-15 12:30:00',
        );
    }

    public function deleteFriendRelation(int $playerId, int $targetPlayerId): ?FriendApply
    {
        $this->deleteCalls[] = [$playerId, $targetPlayerId];

        return $this->deletedRelation;
    }
}
