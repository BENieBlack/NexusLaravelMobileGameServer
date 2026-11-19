<?php

namespace App\Domain\LoginBonus\Services;

use App\Domain\LoginBonus\Constants\LoginBonusConst;
use App\Domain\LoginBonus\DataTransferObjects\VipLoginBonusReReceiveResult;
use App\Exceptions\SystemDataException;
use App\Models\Trx\TrxVipLoginBonus;
use App\Repositories\Mst\VipLoginBonusRepositoryInterface;
use App\Repositories\Sys\SysPlayerRepository;
use App\Repositories\Trx\VipLoginBonusHistoryRepositoryInterface;
use Nexus\Core\Utilities\ClockUtility;
use NexusVip\Services\VipLevelService;

/**
 * VipLoginBonusReReceiveService
 *
 * VIP ログインボーナスの再受け取りの「判定」（要件 R4 ①〜④）
 *
 * login 応答の can_re_receive・POST /login_bonus/list と、POST /login_bonus/receive の再判定が
 * 同じ関数を使う。判定が 2 通りに分かれて「ボタンは出ているのに受け取れない」とならないようにするため。
 * DB へは書かない（配布と履歴の書き込みは ReceiveUseCase が行う）。
 */
class VipLoginBonusReReceiveService
{
    public function __construct(
        private readonly SysPlayerRepository $sysPlayerRepository,
        private readonly VipLevelService $vipLevelService,
        private readonly VipLoginBonusRepositoryInterface $vipBonusRepository,
        private readonly VipLoginBonusHistoryRepositoryInterface $vipHistoryRepository,
    ) {}

    /**
     * 再受け取りの判定
     *
     * 判定の順は ③ マスター → ID 一致（渡されたときだけ）→ ① 今日の段 → ② レベル上昇 → ④ 報酬行。
     * 最初に満たさなかったものを理由にして返す（例外は投げない。呼び出し側がコードに変える）。
     * レベルと受取時のレベルは、呼び出された時点の最新を読む（receive ではプレイヤーロックの中）。
     *
     * @param  string|null  $mstVipLoginBonusId  receive で渡された ID（calendar では null で ID 一致を見ない）
     *
     * @throws SystemDataException プレイヤーが存在しない場合
     */
    public function judge(int $sysPlayerId, ?string $mstVipLoginBonusId = null): VipLoginBonusReReceiveResult
    {
        $sysPlayer = $this->sysPlayerRepository->selectById($sysPlayerId);
        if ($sysPlayer === null) {
            throw SystemDataException::generic('sys_player', $sysPlayerId);
        }

        // 今のレベルは累積 VIP ポイントから算出する（列には持たない）
        $vipLevel = $this->vipLevelService->calculateLevel($sysPlayer->getVipPoint());
        $master = $this->vipBonusRepository->selectActiveByVipLevel($vipLevel);
        $trxVipLoginBonus = $this->vipHistoryRepository->selectLatestByPlayerId($sysPlayerId);

        // ③ 今のレベルの有効な VIP ボーナスがあるか
        if ($master === null) {
            return $this->rejected(LoginBonusConst::REASON_MASTER_NOT_FOUND, $vipLevel, null, $trxVipLoginBonus);
        }

        // 渡された ID が ③ の id と一致するか（存在しない ID・別のレベルの ID もここで落ちる）
        if ($mstVipLoginBonusId !== null && $mstVipLoginBonusId !== $master['id']) {
            return $this->rejected(LoginBonusConst::REASON_MASTER_MISMATCH, $vipLevel, $master, $trxVipLoginBonus);
        }

        // ① 行があり、受取日が今日か。行が無ければ偽（初回配布は login の仕事。R7）
        if ($trxVipLoginBonus === null
            || ! ClockUtility::isSameGameDay(ClockUtility::nowToString(), $trxVipLoginBonus->getReceivedAt())) {
            return $this->rejected(LoginBonusConst::REASON_NOT_GRANTED_TODAY, $vipLevel, $master, $trxVipLoginBonus);
        }

        // ② 受取時のレベルより今のレベルが「高い」ときだけ。同じ・低いは偽（二重受け取りを止める主役）
        if ($trxVipLoginBonus->getVipLevel() >= $vipLevel) {
            return $this->rejected(LoginBonusConst::REASON_LEVEL_NOT_RAISED, $vipLevel, $master, $trxVipLoginBonus);
        }

        // ④ 据え置いた日数が新レベルの loop_days 以内で、その日の報酬行が 1 件以上あるか
        $day = $trxVipLoginBonus->getDay();
        $rewardRows = [];
        if ($day <= (int) $master['loop_days']) {
            $rewardRows = array_values(
                $this->vipBonusRepository->selectContentsByBonusIdAndDay($master['id'], $day)->all()
            );
        }
        if ($rewardRows === []) {
            return $this->rejected(LoginBonusConst::REASON_REWARD_NOT_FOUND, $vipLevel, $master, $trxVipLoginBonus);
        }

        return new VipLoginBonusReReceiveResult(
            canReReceive: true,
            reason: null,
            vipLevel: $vipLevel,
            master: $master,
            trxVipLoginBonus: $trxVipLoginBonus,
            rewardRows: $rewardRows,
        );
    }

    /**
     * 満たさなかったときの結果を作る
     *
     * @param  array<string, mixed>|null  $master
     */
    private function rejected(
        string $reason,
        int $vipLevel,
        ?array $master,
        ?TrxVipLoginBonus $trxVipLoginBonus,
    ): VipLoginBonusReReceiveResult {
        return new VipLoginBonusReReceiveResult(
            canReReceive: false,
            reason: $reason,
            vipLevel: $vipLevel,
            master: $master,
            trxVipLoginBonus: $trxVipLoginBonus,
            rewardRows: [],
        );
    }
}
