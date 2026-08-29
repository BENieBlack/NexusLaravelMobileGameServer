<?php

namespace Tests\Feature\Domain\Gacha;

use App\Domain\Gacha\Services\GachaValidationService;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Models\Mst\MstGacha;
use App\Repositories\Mst\MstGachaRepository;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Utilities\ClockUtility;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * GachaValidationService のテスト
 *
 * ガチャを回す前に弾くべきものを弾けているかを確認する。
 * 開催期間・有効フラグ・日次制限・コスト定義の4つが対象。
 */
class GachaValidationServiceTest extends TestCase
{
    use RefreshMultipleDatabases;

    private const GACHA_ID = 'gacha_validation_001';

    private GachaValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        ClockUtility::setNow('2026-03-15 12:00:00');

        $this->cleanUpMaster();
        $this->service = app(GachaValidationService::class);
    }

    protected function tearDown(): void
    {
        $this->cleanUpMaster();
        ClockUtility::reset();

        parent::tearDown();
    }

    #[Test]
    public function 有効なガチャは取得できる(): void
    {
        $this->makeGacha();

        $mstGacha = $this->service->validateGachaMaster(self::GACHA_ID);

        $this->assertSame(self::GACHA_ID, $mstGacha->id);
    }

    #[Test]
    public function 存在しないガチャは弾く(): void
    {
        $this->expectGameException(GameErrorCode::GACHA_NOT_FOUND);

        $this->service->validateGachaMaster('gacha_no_such_id');
    }

    #[Test]
    public function 無効化されたガチャは弾く(): void
    {
        $this->makeGacha(['is_active' => false]);

        $this->expectGameException(GameErrorCode::GACHA_INACTIVE);

        $this->service->validateGachaMaster(self::GACHA_ID);
    }

    #[Test]
    public function 期間が未設定なら常時開催として通す(): void
    {
        $mstGacha = $this->makeGacha(['start_at' => null, 'end_at' => null]);

        $this->service->validateGachaPeriod($mstGacha);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function 開始前のガチャは弾く(): void
    {
        $mstGacha = $this->makeGacha(['start_at' => '2026-03-15 12:00:01']);

        $this->expectGameException(GameErrorCode::GACHA_NOT_AVAILABLE);

        $this->service->validateGachaPeriod($mstGacha);
    }

    #[Test]
    public function 開始時刻ちょうどは通す(): void
    {
        $mstGacha = $this->makeGacha(['start_at' => '2026-03-15 12:00:00']);

        $this->service->validateGachaPeriod($mstGacha);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function 終了後のガチャは弾く(): void
    {
        $mstGacha = $this->makeGacha(['end_at' => '2026-03-15 11:59:59']);

        $this->expectGameException(GameErrorCode::GACHA_NOT_AVAILABLE);

        $this->service->validateGachaPeriod($mstGacha);
    }

    #[Test]
    public function 終了時刻ちょうどは通す(): void
    {
        $mstGacha = $this->makeGacha(['end_at' => '2026-03-15 12:00:00']);

        $this->service->validateGachaPeriod($mstGacha);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function 日次制限が0なら無制限(): void
    {
        $mstGacha = $this->makeGacha(['daily_limit' => 0]);

        $this->service->validateDailyLimit($mstGacha, 9999);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function 日次制限に達していなければ通す(): void
    {
        $mstGacha = $this->makeGacha(['daily_limit' => 3]);

        $this->service->validateDailyLimit($mstGacha, 2);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function 日次制限に達していれば弾く(): void
    {
        $mstGacha = $this->makeGacha(['daily_limit' => 3]);

        $this->expectGameException(GameErrorCode::GACHA_DAILY_LIMIT_EXCEEDED);

        // 上限と同数でもう引けない
        $this->service->validateDailyLimit($mstGacha, 3);
    }

    #[Test]
    public function 定義済みのコストは取得できる(): void
    {
        $this->makeGacha();
        $this->makeCost(drawCount: 10, costAmount: 3000);

        $cost = $this->service->validateGachaCost(self::GACHA_ID, 10);

        $this->assertSame(3000, $cost->cost_amount);
    }

    #[Test]
    public function 定義の無い実行回数は弾く(): void
    {
        $this->makeGacha();
        $this->makeCost(drawCount: 10, costAmount: 3000);

        $this->expectGameException(GameErrorCode::GACHA_COST_NOT_FOUND);

        // 1連のコストは定義していない
        $this->service->validateGachaCost(self::GACHA_ID, 1);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeGacha(array $overrides = []): MstGacha
    {
        DB::connection('mst')->table('mst_gacha')->insert(array_merge([
            'id' => self::GACHA_ID,
            'is_active' => true,
            'start_at' => null,
            'end_at' => null,
            'daily_limit' => 0,
            'has_step_up' => false,
        ], $overrides));

        $this->refreshMstCache();

        return app(MstGachaRepository::class)->selectById(self::GACHA_ID);
    }

    private function makeCost(int $drawCount, int $costAmount): void
    {
        DB::connection('mst')->table('mst_gacha_cost')->insert([
            'id' => self::GACHA_ID.'_cost_'.$drawCount,
            'mst_gacha_id' => self::GACHA_ID,
            'draw_count' => $drawCount,
            'cost_type' => 'diamond',
            'cost_amount' => $costAmount,
            'is_active' => true,
        ]);

        $this->refreshMstCache();
    }

    private function expectGameException(int $errorCode): void
    {
        $this->expectException(GameException::class);
        $this->expectExceptionCode($errorCode);
    }

    private function cleanUpMaster(): void
    {
        DB::connection('mst')->table('mst_gacha_cost')->where('mst_gacha_id', self::GACHA_ID)->delete();
        DB::connection('mst')->table('mst_gacha')->where('id', self::GACHA_ID)->delete();
        $this->refreshMstCache();
    }
}
