<?php

namespace Tests\Unit\Models\Sys;

use App\Models\Sys\SysFriendApply;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SysFriendApply のテスト
 *
 * 申請の状態遷移。承認済みのものを再度承認したり、却下済みを
 * 申請中として扱ったりすると、フレンド一覧と申請一覧の両方に出る。
 *
 * 状態を変えるだけでDBには書かない（永続化はRepository経由）。
 */
class SysFriendApplyTest extends TestCase
{
    #[Test]
    public function 値を設定して読み出せる(): void
    {
        $apply = new SysFriendApply;
        $apply->setSenderSysPlayerId(1);
        $apply->setReceiverSysPlayerId(2);
        $apply->setStatus(SysFriendApply::STATUS_APPLIED);

        $this->assertSame(1, $apply->getSenderSysPlayerId());
        $this->assertSame(2, $apply->getReceiverSysPlayerId());
        $this->assertSame(SysFriendApply::STATUS_APPLIED, $apply->getStatus());
    }

    #[Test]
    public function 状態はひとつだけ真になる(): void
    {
        // 判定が重なると申請一覧とフレンド一覧の両方に出る
        $cases = [
            SysFriendApply::STATUS_APPLIED => 'isApplied',
            SysFriendApply::STATUS_ACCEPTED => 'isAccepted',
            SysFriendApply::STATUS_REJECTED => 'isRejected',
            SysFriendApply::STATUS_DELETED => 'isDeleted',
        ];

        foreach ($cases as $status => $expectedTrue) {
            $apply = $this->makeApply($status);

            foreach ($cases as $method) {
                $this->assertSame(
                    $method === $expectedTrue,
                    $apply->{$method}(),
                    "{$status} で {$method} の判定がおかしい"
                );
            }
        }
    }

    #[Test]
    public function 削除済みに落とせる(): void
    {
        // 状態を変えるだけでDBには書かない
        $apply = $this->makeApply(SysFriendApply::STATUS_ACCEPTED);

        $apply->markAsDeleted();

        $this->assertTrue($apply->isDeleted());
        $this->assertFalse($apply->isAccepted());
    }

    #[Test]
    public function 利用可能なステータスの一覧を取れる(): void
    {
        $this->assertSame(
            [
                SysFriendApply::STATUS_APPLIED,
                SysFriendApply::STATUS_ACCEPTED,
                SysFriendApply::STATUS_REJECTED,
                SysFriendApply::STATUS_DELETED,
            ],
            SysFriendApply::availableStatuses()
        );
    }

    #[Test]
    public function レスポンス用の配列ではidに接頭辞が付く(): void
    {
        $apply = $this->makeApply(SysFriendApply::STATUS_APPLIED);
        $apply->setAttribute('id', 11);

        $array = $apply->toResponseArray();

        $this->assertSame(11, $array['sys_friend_apply_id']);
        $this->assertArrayNotHasKey('id', $array);
    }

    private function makeApply(string $status): SysFriendApply
    {
        $apply = new SysFriendApply;
        $apply->setSenderSysPlayerId(1);
        $apply->setReceiverSysPlayerId(2);
        $apply->setStatus($status);

        return $apply;
    }
}
