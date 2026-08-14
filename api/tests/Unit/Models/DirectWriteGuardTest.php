<?php

namespace Tests\Unit\Models;

use App\Models\Sys\SysPlayer;
use Nexus\Core\Exceptions\DirectWriteNotAllowedException;
use Nexus\Core\Models\_BaseModel;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * Eloquentの即時書き込みガードの検証
 *
 * Sys/Trx/LogのデータはUnitOfWork経由で永続化する規約であり、
 * $model->save() による即時書き込みは実行時に拒否される。
 */
class DirectWriteGuardTest extends TestCase
{
    use RefreshMultipleDatabases;

    #[Test]
    public function test_save_is_rejected_when_direct_writes_are_disallowed(): void
    {
        // 本番の実行時経路と同じ状態にする
        _BaseModel::disallowDirectWrites();

        $sysPlayer = new SysPlayer([
            'uuid' => 'guard-test-uuid',
            'my_id' => 'grd00001',
            'name' => 'Guard Test',
        ]);

        $this->expectException(DirectWriteNotAllowedException::class);
        $this->expectExceptionMessage('Eloquentによる即時書き込みは禁止されています');

        $sysPlayer->save();
    }

    #[Test]
    public function test_save_is_allowed_inside_allow_direct_writes_callback(): void
    {
        _BaseModel::disallowDirectWrites();

        $sysPlayer = new SysPlayer([
            'uuid' => 'guard-test-uuid-allowed',
            'my_id' => 'grd00002',
            'name' => 'Guard Test Allowed',
        ]);

        $result = _BaseModel::allowDirectWrites(fn () => $sysPlayer->save());

        $this->assertTrue($result);
        $this->assertTrue($sysPlayer->exists);
    }

    #[Test]
    public function test_allow_direct_writes_callback_restores_previous_state(): void
    {
        _BaseModel::disallowDirectWrites();

        _BaseModel::allowDirectWrites(fn () => null);

        $this->assertFalse(
            _BaseModel::directWritesAllowed(),
            'コールバック実行後は元の禁止状態に戻るべき'
        );
    }

    protected function tearDown(): void
    {
        // 後続テストのフィクスチャ投入のため許可状態へ戻す
        _BaseModel::allowDirectWrites();

        parent::tearDown();
    }
}
