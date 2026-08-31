<?php

namespace NexusFriend\Tests\Unit\Constants;

use NexusFriend\Constants\FriendStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * FriendStatus のテスト
 *
 * 申請の状態を表す定数。DBのenumと値が揃っていないと、
 * 保存はできても読み出した状態の判定が全部falseになる。
 */
class FriendStatusTest extends TestCase
{
    #[Test]
    public function 全ての状態を取れる(): void
    {
        $this->assertSame(
            [
                FriendStatus::APPLIED,
                FriendStatus::ACCEPTED,
                FriendStatus::REJECTED,
                FriendStatus::DELETED,
            ],
            FriendStatus::all()
        );
    }

    #[Test]
    public function 定義された状態だけを有効とみなす(): void
    {
        foreach (FriendStatus::all() as $status) {
            $this->assertTrue(FriendStatus::isValid($status), "{$status} が無効扱い");
        }

        $this->assertFalse(FriendStatus::isValid('NoSuchStatus'));
    }

    #[Test]
    public function 大文字小文字は区別する(): void
    {
        // DBのenumはパスカルケース。小文字で入れると保存できない
        $this->assertFalse(FriendStatus::isValid('applied'));
        $this->assertFalse(FriendStatus::isValid('APPLIED'));
    }
}
