<?php

namespace App\Domain\LoginBonus\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\LoginBonus\Constants\LoginBonusConst;
use App\Domain\LoginBonus\Contracts\VipLoginBonusReceiveRecorderInterface;
use App\Domain\LoginBonus\Responses\ReceiveResponse;
use App\Domain\LoginBonus\Services\VipLoginBonusReReceiveService;
use App\Domain\LoginBonus\Support\VipLoginBonusReReceiveResult;
use App\Exceptions\BusinessLogicException;
use App\Exceptions\MasterDataException;
use App\Exceptions\SystemDataException;
use App\Models\Trx\TrxVipLoginBonus;
use App\Repositories\Trx\VipLoginBonusHistoryRepositoryInterface;
use Nexus\Core\ResourceDelivery\Services\ResourceDeliveryService;
use Nexus\Core\Utilities\ClockUtility;
use NexusLogin\Services\LoginBonusResourceConverter;

/**
 * ReceiveUseCase
 *
 * VIP ログインボーナスの再受け取り。今日すでに受け取った後に VIP レベルが上がったとき、
 * 据え置いた日数（trx_vip_login_bonus.day）の「新しいレベルの報酬」をもう一度配る。
 *
 * 排他: 自分のデータ（自分の trx・log）しか触らないので、行ロックは使わない。
 * プレイヤーロック（player.lock）が同じプレイヤーのリクエストを直列にし、判定（validation）も
 * そのロックの中で行う。vip_level の更新を配布と同じトランザクションにすることで、
 * 同時・再送の 2 回目は ② が偽になり 26001 で止まる（二重に配らない）。
 */
class ReceiveUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly VipLoginBonusReReceiveService $reReceiveService,
        private readonly ResourceDeliveryService $resourceDeliveryService,
        private readonly VipLoginBonusReceiveRecorderInterface $receiveRecorder,
        private readonly VipLoginBonusHistoryRepositoryInterface $vipHistoryRepository,
    ) {}

    /**
     * 再受け取りできるかを検証する（配る前。DB は変わらない）
     *
     * @return VipLoginBonusReReceiveResult 通過した判定の結果（今のレベル・マスター・受け取り状態・報酬行。handle() で再取得しない）
     *
     * @throws SystemDataException プレイヤーが存在しない場合
     * @throws BusinessLogicException 26001 今日の段でない、またはレベルが上がっていない
     * @throws MasterDataException 26002 今のレベルのボーナスが無い／ID が一致しない、26003 報酬行が無い
     */
    public function validation(int $sysPlayerId, string $mstVipLoginBonusId): VipLoginBonusReReceiveResult
    {
        // judge() がプレイヤーの存在確認・今のレベルの算出・R4 ①〜④ を行う（レベルはロックの中で読む）
        $judge = $this->reReceiveService->judge($sysPlayerId, $mstVipLoginBonusId);

        if ($judge->canReReceive) {
            return $judge;
        }

        // 理由 → エラーコード。26001 の 2 つの理由はクライアントの対応が同じ（ログとテストの切り分け用）
        $reason = (string) $judge->reason;
        throw match ($reason) {
            LoginBonusConst::REASON_MASTER_NOT_FOUND,
            LoginBonusConst::REASON_MASTER_MISMATCH => MasterDataException::loginBonusMaster($mstVipLoginBonusId),
            LoginBonusConst::REASON_REWARD_NOT_FOUND => MasterDataException::loginBonusReward(
                (string) ($judge->master['id'] ?? $mstVipLoginBonusId),
                (int) $judge->trxVipLoginBonus?->getDay(),
            ),
            LoginBonusConst::REASON_NOT_GRANTED_TODAY,
            LoginBonusConst::REASON_LEVEL_NOT_RAISED => BusinessLogicException::loginBonusReReceiveNotAvailable($reason),
            // judge() が新しい理由を返すようになったのに、ここへの対応を忘れたときに気づけるよう、黙って 26001 にしない
            default => throw new \LogicException("未対応の再受け取りの理由: {$reason}"),
        };
    }

    /**
     * @throws \Throwable
     */
    public function handle(int $sysPlayerId, string $mstVipLoginBonusId): ReceiveResponse
    {
        // 配る前に検証（満たさなければ例外。トランザクションに入らないのでキューも空）
        $judge = $this->validation($sysPlayerId, $mstVipLoginBonusId);

        return $this->executeWithTransaction(function () use ($sysPlayerId, $judge) {
            /** @var array<string, mixed> $master */
            $master = $judge->master;
            $day = (int) $judge->trxVipLoginBonus?->getDay();

            // 据え置いた日数の、新しいレベルの報酬。通常の配布と同じ変換・同じ配布経路
            // （ResourceDeliveryService。図鑑などの Handler も同じに反応する）
            $contents = LoginBonusResourceConverter::toContents($judge->rewardRows);
            $resources = $contents->map(
                fn ($content) => LoginBonusResourceConverter::toResource($content)
            )->all();
            $this->resourceDeliveryService->addResources($resources);
            $this->resourceDeliveryService->deliver($sysPlayerId);

            // 履歴: trx_vip_login_bonus を新しいレベル・日数を据え置き・今日の日の開始で更新し、
            // log に 1 報酬 1 行足す（login の配布と同じ書き込みを共有）
            $this->receiveRecorder->recordReceive(
                sysPlayerId: $sysPlayerId,
                mstVipLoginBonusId: (string) $master['id'],
                day: $day,
                vipLevel: $judge->vipLevel,
                receivedAt: ClockUtility::calcGameDayStart(ClockUtility::nowToString())->format('Y-m-d H:i:s'),
                contents: $contents,
            );

            // 応答用: 履歴を書いた後の trx_vip_login_bonus（同じトランザクションの中なので積んだ更新が見える）と、今のレベルの段
            /** @var TrxVipLoginBonus $trxVipLoginBonus */
            $trxVipLoginBonus = $this->vipHistoryRepository->selectLatestByPlayerId($sysPlayerId);

            return new ReceiveResponse(
                $trxVipLoginBonus,
                array_values($resources),
                vipBonusId: (string) $master['id'],
                vipLevel: $judge->vipLevel,
                lastReceivedDay: $day,
            );
        });
    }
}
