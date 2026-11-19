<?php

namespace App\Domain\LoginBonus\Services;

use App\Domain\Common\DataTransferObjects\LoginBonusCalendarResult;
use App\Domain\Common\DataTransferObjects\LoginBonusCalendarsResult;
use App\Domain\Common\DataTransferObjects\LoginBonusDayRewardResult;
use App\Domain\Common\DataTransferObjects\LoginBonusRewardResult;
use App\Domain\Common\DataTransferObjects\VipLoginBonusCalendarResult;
use App\Domain\LoginBonus\Contracts\LoginBonusCalendarInterface;
use App\Repositories\Mst\VipLoginBonusRepositoryInterface;
use Illuminate\Support\Facades\Log;
use Nexus\Core\Utilities\ClockUtility;
use NexusLogin\Repositories\LoginBonusHistoryRepositoryInterface;
use NexusLogin\Repositories\LoginBonusRepositoryInterface;
use NexusLogin\Services\LoginBonusResourceConverter;

/**
 * LoginBonusCalendarService
 *
 * ログインボーナスのカレンダー表示用に、2 つの段（通常・VIP）の「状態」を作って DTO で返す。
 * - 全日（1〜loop_days）の報酬を、配布と同じ変換（LoginBonusResourceConverter）・同じ並びで作る
 * - is_granted_today / is_granted_now / can_re_receive を、段ごとに 1 回だけ判定する
 * DB へは書かない。API の形（配列）への整形は LoginBonusCalendarFormatter が行う。
 */
class LoginBonusCalendarService implements LoginBonusCalendarInterface
{
    public function __construct(
        private readonly LoginBonusRepositoryInterface $loginBonusRepository,
        private readonly LoginBonusHistoryRepositoryInterface $loginBonusHistoryRepository,
        private readonly VipLoginBonusRepositoryInterface $vipBonusRepository,
        private readonly VipLoginBonusReReceiveService $reReceiveService,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function findCalendars(
        int $sysPlayerId,
        bool $isNormalGrantedNowAllowed,
        bool $isVipGrantedNowAllowed,
    ): LoginBonusCalendarsResult {
        return new LoginBonusCalendarsResult(
            normal: $this->findNormal($sysPlayerId, $isNormalGrantedNowAllowed),
            vip: $this->findVip($sysPlayerId, $isVipGrantedNowAllowed),
        );
    }

    /**
     * 通常の段。有効（かつイベント期間内）な通常ログインボーナスが無いときだけ null
     */
    private function findNormal(int $sysPlayerId, bool $isGrantedNowAllowed): ?LoginBonusCalendarResult
    {
        // 配布と同じ引き方（イベント期間も見る）で 1 件
        $bonus = $this->loginBonusRepository->selectActiveDailyBonus();
        if ($bonus === null) {
            return null;
        }

        // 最後に配った日数と受取日（login 後は同じリクエストで積んだ未保存の更新も見える）
        $history = $this->loginBonusHistoryRepository->selectLatestByPlayerId($sysPlayerId);
        $isGrantedToday = $history !== null && $this->isToday($history->getReceivedDate());

        $loopDays = (int) $bonus['loop_days'];

        return new LoginBonusCalendarResult(
            mstLoginBonusId: (string) $bonus['id'],
            loopDays: $loopDays,
            lastReceivedDay: $history?->getDay(),
            isGrantedToday: $isGrantedToday,
            // 「今回の login の自動配布が走った」ときだけ真。受取日が今日になっているのは
            // 初回の login では自動配布が書いたときだけなので、初回判定 かつ is_granted_today で決める
            isGrantedNow: $isGrantedNowAllowed && $isGrantedToday,
            dayRewardList: $this->buildDayRewardList(
                $loopDays,
                $this->loginBonusRepository->selectDailyContentsByLoginBonusId((string) $bonus['id']),
                (string) $bonus['id'],
            ),
        );
    }

    /**
     * VIP の段。今のレベルに有効な VIP ボーナスが無いときだけ null
     */
    private function findVip(int $sysPlayerId, bool $isGrantedNowAllowed): ?VipLoginBonusCalendarResult
    {
        // can_re_receive と receive の再判定は同じ judge()。今のレベル・マスター・受け取り状態もここから取る
        $judge = $this->reReceiveService->judge($sysPlayerId);
        $master = $judge->master;
        if ($master === null) {
            return null;
        }

        $trx = $judge->trxVipLoginBonus;
        // マスターの ID が受け取り済みの行と違っても（差し替え）、受取日だけで判定する
        $isGrantedToday = $trx !== null && $this->isToday($trx->getReceivedAt());

        $loopDays = (int) $master['loop_days'];
        $mstVipLoginBonusId = (string) $master['id'];

        return new VipLoginBonusCalendarResult(
            mstVipLoginBonusId: $mstVipLoginBonusId,
            loopDays: $loopDays,
            // 受取済みの行の vip_level ではなく、今のレベル（Q6・C8b）
            vipLevel: $judge->vipLevel,
            lastReceivedDay: $trx?->getDay(),
            isGrantedToday: $isGrantedToday,
            isGrantedNow: $isGrantedNowAllowed && $isGrantedToday,
            canReReceive: $judge->canReReceive,
            dayRewardList: $this->buildDayRewardList(
                $loopDays,
                $this->vipBonusRepository->selectContentsByBonusId($mstVipLoginBonusId)->all(),
                $mstVipLoginBonusId,
            ),
        );
    }

    /**
     * 受取日が今日（今のゲーム日）か
     *
     * 文字列の一致ではなく isSameGameDay で比べる。配布の判定（last_login_at の isSameGameDay）と同じ基準になり、
     * DAY_START_TIME を変えた直後でも食い違わない。
     */
    private function isToday(string $receivedAt): bool
    {
        return ClockUtility::isSameGameDay(ClockUtility::nowToString(), $receivedAt);
    }

    /**
     * 全日（1〜loop_days）の報酬を作る。報酬行の無い日も要素（reward_list = []）として残す
     *
     * 行は呼び出し側が「配る順」に並べて渡す。ここでは並べ替えない（日ごとにグループ化するだけ）。
     * 未知の content_type（ResourceType に無い値）の行はその行だけ除いて警告ログを残し、login は失敗させない
     * （配布側の失敗は今のまま。配る日の行が不正なら配る日に失敗する）。
     *
     * @param  iterable<array<string, mixed>>  $rows  全日の報酬行（day → 配る順）
     * @return array<int, LoginBonusDayRewardResult>
     */
    private function buildDayRewardList(int $loopDays, iterable $rows, string $mstBonusId): array
    {
        /** @var array<int, array<int, LoginBonusRewardResult>> $rewardsByDay */
        $rewardsByDay = [];

        foreach ($rows as $row) {
            $day = (int) $row['day'];
            // 範囲外の日（day = 0 のカムバック用や loop_days 超過）は表に出さない
            if ($day < 1 || $day > $loopDays) {
                continue;
            }

            try {
                // 配布と同じ変換。type の決め方（wallet は content_mst_id が type）を 1 つにする
                $resource = LoginBonusResourceConverter::toResource(LoginBonusResourceConverter::toContent($row));
            } catch (\ValueError $e) {
                // 他の例外は握りつぶさない。ValueError（未知の content_type）だけ捕まえる
                Log::warning('Login bonus calendar: skipped a reward row with an invalid content_type', [
                    'mst_bonus_id' => $mstBonusId,
                    'day' => $day,
                    'content_type' => $row['content_type'] ?? null,
                ]);

                continue;
            }

            $rewardsByDay[$day][] = new LoginBonusRewardResult(
                type: $resource->getTypeValue(),
                id: $resource->getId(),
                amount: $resource->getAmount(),
                metadata: $resource->getMetadata(),
            );
        }

        $dayRewardList = [];
        for ($day = 1; $day <= $loopDays; $day++) {
            $dayRewardList[] = new LoginBonusDayRewardResult($day, $rewardsByDay[$day] ?? []);
        }

        return $dayRewardList;
    }
}
