<?php

namespace Tests\Unit\Adapters\Friend;

use App\Adapters\Friend\FriendApplyAdapter;
use App\Models\Sys\SysFriendApply;
use Nexus\Core\Models\_BaseModel;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * FriendApplyAdapter の Model→DTO 変換テスト
 *
 * Modelのタイムスタンプ取得やDTOの型が実際に噛み合うことを検証する。
 */
class FriendApplyAdapterTest extends TestCase
{
    use RefreshMultipleDatabases;

    #[Test]
    public function test_converts_model_to_dto(): void
    {
        $model = $this->makeApply();

        $dto = FriendApplyAdapter::toDto($model);

        $this->assertSame($model->getId(), $dto->getId());
        $this->assertSame(101, $dto->getSenderPlayerId());
        $this->assertSame(202, $dto->getReceiverPlayerId());
        $this->assertSame(SysFriendApply::STATUS_APPLIED, $dto->getStatus());
    }

    #[Test]
    public function test_timestamps_are_returned_as_string(): void
    {
        $dto = FriendApplyAdapter::toDto($this->makeApply());

        // 日時は文字列のまま扱う（Carbonへのキャストを強制しない）
        $this->assertIsString($dto->getCreatedAt());
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
            $dto->getCreatedAt()
        );
        $this->assertIsString($dto->getUpdatedAt());
    }

    #[Test]
    public function test_converts_model_array_to_dto_array(): void
    {
        $dtos = FriendApplyAdapter::toDtoArray([$this->makeApply(), $this->makeApply()]);

        $this->assertCount(2, $dtos);
    }

    private function makeApply(): SysFriendApply
    {
        return _BaseModel::allowDirectWrites(function () {
            $model = new SysFriendApply([
                'sender_sys_player_id' => 101,
                'receiver_sys_player_id' => 202,
                'status' => SysFriendApply::STATUS_APPLIED,
            ]);
            $model->save();

            return $model;
        });
    }
}
