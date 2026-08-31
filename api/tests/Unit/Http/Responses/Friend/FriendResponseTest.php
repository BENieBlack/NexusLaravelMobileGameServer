<?php

namespace Tests\Unit\Http\Responses\Friend;

use App\Http\Responses\Friend\ApplyAcceptResponse;
use App\Http\Responses\Friend\ApplyRejectResponse;
use App\Http\Responses\Friend\ApplySendResponse;
use App\Models\Sys\SysFriendApply;
use NexusFriend\DataTransferObjects\FriendApply;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * フレンド申請系レスポンスのテスト
 *
 * ModelからとDTOからの2つの入口があり、どちらから作っても
 * 同じ形になる必要がある。片方だけ直すとエンドポイントごとに
 * 応答の形が変わる。
 *
 * IDキーはどのテーブルのIDか分かる名前で返す。
 */
class FriendResponseTest extends TestCase
{
    #[Test]
    public function 申請送信の応答をdtoから作れる(): void
    {
        $array = ApplySendResponse::fromDto($this->makeDto())->toArray();

        $this->assertSame([
            'sys_friend_apply_id' => 11,
            'sender_sys_player_id' => 1,
            'receiver_sys_player_id' => 2,
            'status' => SysFriendApply::STATUS_APPLIED,
            'created_at' => '2026-03-15 12:00:00',
        ], $array);
    }

    #[Test]
    public function 申請送信の応答はmodelからも同じ形になる(): void
    {
        $this->assertSame(
            ApplySendResponse::fromDto($this->makeDto())->toArray(),
            ApplySendResponse::fromModel($this->makeModel())->toArray()
        );
    }

    #[Test]
    public function 承認の応答は更新日時も返す(): void
    {
        // いつ承認されたかをクライアントが表示する
        $array = ApplyAcceptResponse::fromDto($this->makeDto(SysFriendApply::STATUS_ACCEPTED))->toArray();

        $this->assertSame(SysFriendApply::STATUS_ACCEPTED, $array['status']);
        $this->assertSame('2026-03-15 12:30:00', $array['updated_at']);
    }

    #[Test]
    public function 承認の応答はmodelからも同じ形になる(): void
    {
        $this->assertSame(
            ApplyAcceptResponse::fromDto($this->makeDto(SysFriendApply::STATUS_ACCEPTED))->toArray(),
            ApplyAcceptResponse::fromModel($this->makeModel(SysFriendApply::STATUS_ACCEPTED))->toArray()
        );
    }

    #[Test]
    public function 却下の応答は却下済みの状態を返す(): void
    {
        $array = ApplyRejectResponse::fromDto($this->makeDto(SysFriendApply::STATUS_REJECTED))->toArray();

        $this->assertSame(11, $array['sys_friend_apply_id']);
        $this->assertSame(SysFriendApply::STATUS_REJECTED, $array['status']);
    }

    #[Test]
    public function 却下の応答はmodelからも同じ形になる(): void
    {
        $this->assertSame(
            ApplyRejectResponse::fromDto($this->makeDto(SysFriendApply::STATUS_REJECTED))->toArray(),
            ApplyRejectResponse::fromModel($this->makeModel(SysFriendApply::STATUS_REJECTED))->toArray()
        );
    }

    #[Test]
    public function idキーはテーブル名で修飾する(): void
    {
        // どのテーブルのIDか分からない 'id' は返さない
        foreach ([
            ApplySendResponse::fromDto($this->makeDto())->toArray(),
            ApplyAcceptResponse::fromDto($this->makeDto())->toArray(),
            ApplyRejectResponse::fromDto($this->makeDto())->toArray(),
        ] as $array) {
            $this->assertArrayHasKey('sys_friend_apply_id', $array);
            $this->assertArrayNotHasKey('id', $array);
        }
    }

    private function makeDto(string $status = SysFriendApply::STATUS_APPLIED): FriendApply
    {
        return new FriendApply(
            id: 11,
            senderPlayerId: 1,
            receiverPlayerId: 2,
            status: $status,
            createdAt: '2026-03-15 12:00:00',
            updatedAt: '2026-03-15 12:30:00',
        );
    }

    private function makeModel(string $status = SysFriendApply::STATUS_APPLIED): SysFriendApply
    {
        $model = new SysFriendApply;
        $model->setRawAttributes([
            'id' => 11,
            'sender_sys_player_id' => 1,
            'receiver_sys_player_id' => 2,
            'status' => $status,
            'created_at' => '2026-03-15 12:00:00',
            'updated_at' => '2026-03-15 12:30:00',
        ], true);
        $model->exists = true;

        return $model;
    }
}
