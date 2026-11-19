<?php

namespace Tests\Unit\Domain\LoginBonus;

use App\Domain\LoginBonus\Constants\LoginBonusConst;
use App\Domain\LoginBonus\Contracts\LoginBonusCalendarInterface;
use App\Domain\LoginBonus\Services\VipLoginBonusReReceiveService;
use App\Domain\LoginBonus\Support\VipLoginBonusReReceiveResult;
use App\Domain\LoginBonus\UseCases\ReceiveUseCase;
use App\Exceptions\BusinessLogicException;
use App\Exceptions\GameErrorCode;
use App\Exceptions\MasterDataException;
use App\Repositories\Trx\VipLoginBonusHistoryRepositoryInterface;
use App\Repositories\Trx\VipLoginBonusReceiveRecordRepository;
use Nexus\Core\ResourceDelivery\Services\ResourceDeliveryService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * ReceiveUseCase::validation の「判定の理由 → エラーコード」の対応のテスト
 *
 * 判定（judge）は DB を読むので別のテストで見る。ここでは judge の結果を差し替え、
 * 理由ごとのコードと、未知の理由が黙って 26001 にならないことだけを固定する。
 */
class ReceiveUseCaseValidationTest extends TestCase
{
    /**
     * @return array<string, array{string, int}>
     */
    public static function reasonProvider(): array
    {
        return [
            '今日の段でない' => [LoginBonusConst::REASON_NOT_GRANTED_TODAY, GameErrorCode::LOGIN_BONUS_RE_RECEIVE_NOT_AVAILABLE],
            'レベルが上がっていない' => [LoginBonusConst::REASON_LEVEL_NOT_RAISED, GameErrorCode::LOGIN_BONUS_RE_RECEIVE_NOT_AVAILABLE],
            '今のレベルのマスターが無い' => [LoginBonusConst::REASON_MASTER_NOT_FOUND, GameErrorCode::LOGIN_BONUS_MASTER_NOT_FOUND],
            'ID が一致しない' => [LoginBonusConst::REASON_MASTER_MISMATCH, GameErrorCode::LOGIN_BONUS_MASTER_NOT_FOUND],
            '報酬行が無い' => [LoginBonusConst::REASON_REWARD_NOT_FOUND, GameErrorCode::LOGIN_BONUS_REWARD_NOT_FOUND],
        ];
    }

    #[Test]
    #[DataProvider('reasonProvider')]
    public function 理由ごとに決まったエラーコードになる(string $reason, int $expectedCode): void
    {
        $useCase = $this->useCaseRejectedWith($reason);

        try {
            $useCase->validation(1, 'vip_login_lv1');
            $this->fail('例外になるはず');
        } catch (BusinessLogicException|MasterDataException $e) {
            $this->assertSame($expectedCode, $e->getErrorCode());
        }
    }

    #[Test]
    public function 未知の理由は26001にせず_logic_exceptionになる(): void
    {
        $useCase = $this->useCaseRejectedWith('unknown_reason');

        $this->expectException(\LogicException::class);

        $useCase->validation(1, 'vip_login_lv1');
    }

    private function useCaseRejectedWith(string $reason): ReceiveUseCase
    {
        $judge = $this->createMock(VipLoginBonusReReceiveService::class);
        $judge->method('judge')->willReturn(new VipLoginBonusReReceiveResult(
            canReReceive: false,
            reason: $reason,
            vipLevel: 1,
            master: ['id' => 'vip_login_lv1', 'loop_days' => 7],
            trxVipLoginBonus: null,
            rewardRows: [],
        ));

        return new ReceiveUseCase(
            $judge,
            $this->createMock(LoginBonusCalendarInterface::class),
            $this->createMock(ResourceDeliveryService::class),
            $this->createMock(VipLoginBonusReceiveRecordRepository::class),
            $this->createMock(VipLoginBonusHistoryRepositoryInterface::class),
        );
    }
}
