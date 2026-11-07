<?php

namespace App\Repositories\Mst;

use App\Models\Mst\MstLoginBonus;
use App\Models\Mst\MstLoginBonusContent;
use App\Repositories\Trx\TrxLoginBonusRepository;
use Nexus\Core\Utilities\ClockUtility;
use NexusEvent\Constants\EventContentType;
use NexusEvent\Services\EventPeriodResolver;
use NexusLogin\Repositories\LoginBonusRepositoryInterface;

/**
 * LoginBonusRepositoryAdapter
 *
 * Eloquent ORMを使用したログインボーナスマスタデータへのアクセス実装
 */
class LoginBonusRepositoryAdapter implements LoginBonusRepositoryInterface
{
    public function __construct(
        private readonly TrxLoginBonusRepository $trxLoginBonusRepository,
        private readonly EventPeriodResolver $eventPeriodResolver,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function selectLoopDaysForActiveBonus(): ?int
    {
        $mstLoginBonus = $this->firstOpenDailyBonus();

        return $mstLoginBonus === null ? null : $mstLoginBonus->getLoopDays();
    }

    /**
     * {@inheritDoc}
     */
    public function selectActiveByDay(int $day): ?array
    {
        $mstLoginBonus = $this->firstOpenDailyBonus();

        return $mstLoginBonus ? $mstLoginBonus->toArray() : null;
    }

    /**
     * 有効な通常ログインボーナスを取得
     *
     * @return array|null ログインボーナス設定
     */
    /**
     * @return array<string, mixed>|null
     */
    public function selectActiveDailyBonus(): ?array
    {
        $mstLoginBonus = $this->firstOpenDailyBonus();

        return $mstLoginBonus ? $mstLoginBonus->toArray() : null;
    }

    /**
     * {@inheritDoc}
     */
    public function selectContentsByLoginBonusId(string $loginBonusId): array
    {
        return MstLoginBonusContent::where('mst_login_bonus_id', $loginBonusId)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($content) => $content->toArray())
            ->all();
    }

    /**
     * {@inheritDoc}
     */
    public function selectEligibleComebackBonus(int $absentDays): ?array
    {
        // 期間は、イベントに入っていればイベントの期間で判定するため、SQLで絞らずPHPで判定する
        $mstLoginBonus = MstLoginBonus::comebackType()
            ->active()
            ->where('required_absent_days', '<=', $absentDays)
            ->orderByDesc('priority')
            ->orderByDesc('required_absent_days')
            ->get()
            ->first(fn (MstLoginBonus $candidate) => $this->isComebackBonusOpen($candidate));

        return $mstLoginBonus ? $mstLoginBonus->toArray() : null;
    }

    /**
     * {@inheritDoc}
     */
    public function selectActiveComebackBonusById(string $loginBonusId): ?array
    {
        $mstLoginBonus = MstLoginBonus::comebackType()
            ->active()
            ->whereKey($loginBonusId)
            ->first();

        return $mstLoginBonus !== null && $this->isComebackBonusOpen($mstLoginBonus) ? $mstLoginBonus->toArray() : null;
    }

    /**
     * {@inheritDoc}
     *
     * 受け取り状態は trx_login_bonus（プレイヤー×タイプごとに1行）にあるため TrxLoginBonusRepository で引く
     */
    public function hasReceivedComebackBonusRecently(
        int $sysPlayerId,
        string $comebackBonusId,
        int $validDays
    ): bool {
        $validFrom = ClockUtility::now()->subDays($validDays)->format('Y-m-d H:i:s');

        $trxLoginBonus = $this->trxLoginBonusRepository->selectByType($sysPlayerId, MstLoginBonus::TYPE_COMEBACK);

        return $trxLoginBonus !== null
            && $trxLoginBonus->getMstLoginBonusId() === $comebackBonusId
            && $trxLoginBonus->getReceivedDate() >= $validFrom;
    }

    /**
     * ログインボーナスIDと日数でコンテンツを取得
     *
     * @param  string  $loginBonusId  ログインボーナスID
     * @param  int  $day  日数
     * @return array コンテンツの配列
     */
    /**
     * @return array<int, array<string, mixed>>
     */
    public function selectContentsByLoginBonusIdAndDay(string $loginBonusId, int $day): array
    {
        $bonusType = MstLoginBonus::whereKey($loginBonusId)->value('type');
        $contentQuery = MstLoginBonusContent::where('mst_login_bonus_id', $loginBonusId);

        if ($bonusType === MstLoginBonus::TYPE_DAILY) {
            $contentQuery->where('day', $day);
        } else {
            // カムバック報酬は日次サイクルではないため、day=0の内容を返す。
            $contentQuery->where('day', 0);
        }

        return $contentQuery
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($content) => $content->toArray())
            ->all();
    }

    /**
     * 有効な通常ログインボーナスのうち、最初の1件
     *
     * 通常ログインボーナスは、イベントに入っていなければ、期間を見ない（従来どおり）。
     * イベントに入っていれば、イベントの期間の外では無いものとして扱う。
     */
    private function firstOpenDailyBonus(): ?MstLoginBonus
    {
        return MstLoginBonus::dailyType()
            ->active()
            ->get()
            ->first(function (MstLoginBonus $mstLoginBonus) {
                $period = $this->eventPeriodResolver->resolve(EventContentType::LOGIN_BONUS, (string) $mstLoginBonus->getKey());

                return $period === null || ClockUtility::isWithin($period->getStartAt(), $period->getEndAt());
            });
    }

    /**
     * カムバックボーナスが今の期間内か
     *
     * イベントに入っていればイベントの期間、入っていなければ自身の start_at / end_at（NULLは制限なし）で判定する。
     */
    private function isComebackBonusOpen(MstLoginBonus $mstLoginBonus): bool
    {
        $period = $this->eventPeriodResolver->resolve(EventContentType::LOGIN_BONUS, (string) $mstLoginBonus->getKey());

        if ($period !== null) {
            return ClockUtility::isWithin($period->getStartAt(), $period->getEndAt());
        }

        return ClockUtility::isWithin($mstLoginBonus->getRawOriginal('start_at'), $mstLoginBonus->getRawOriginal('end_at'));
    }
}
