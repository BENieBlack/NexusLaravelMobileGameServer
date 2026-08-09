<?php

namespace NexusLogin\Services;

use NexusResource\DTOs\ResourceDto;
use NexusResourceDelivery\Services\ResourceDeliveryService;
use NexusLogin\Contracts\LoginBonusStrategyInterface;
use Carbon\CarbonImmutable;
use Nexus\Core\Utilities\ClockUtility;
use Nexus\Core\Support\CustomCollection;

/**
 * _BaseLoginBonusService
 *
 * ログインボーナス全般の基本処理を提供する抽象クラス
 * Template Method Patternを使用して、拡張可能な設計を実現
 * 
 * **デフォルト動作: 通常ログインボーナス（ループ有、スキップなし）**
 * 
 * Package側: 通常ログインボーナスの動作をデフォルト実装
 * Domain側: 差分のみオーバーライドして使用
 * 
 * オーバーライド可能なメソッド:
 * - isEligible(): 配布対象判定ロジック（必須）
 * - shouldLoop(): ループするかどうか（デフォルト: true）
 * - shouldSkipOnAbsence(): 休眠時にスキップするかどうか（デフォルト: false）
 * - calculateCurrentDay(): 現在の受け取り日数を計算
 * - getLoginBonusData(): ボーナスデータ取得ロジック（必須）
 * - getBonusContents(): 報酬内容取得ロジック（必須）
 * - recordHistory(): 履歴記録ロジック（必須）
 * - beforeGrant(): 配布前の追加チェック
 * - afterGrant(): 配布後の追加処理
 * - convertToResource(): リソース変換のカスタマイズ
 */
abstract class _BaseLoginBonusService implements LoginBonusStrategyInterface
{
    public function __construct(
        protected readonly ResourceDeliveryService $resourceDeliveryService,
    ) {
    }

    /**
     * {@inheritDoc}
     * 
     * サブクラスで実装必須
     */
    abstract public function isEligible(int $sysPlayerId, ?string $lastLoginAt): bool;

    /**
     * ログインボーナスデータを取得（サブクラスで実装必須）
     * 
     * @param int $sysPlayerId プレイヤーID
     * @param int $currentDay 現在の受け取り日数
     * @param string|null $lastLoginAt 最終ログイン日時
     * @return array|null ボーナスデータ、存在しない場合はnull
     */
    abstract protected function getLoginBonusData(int $sysPlayerId, int $currentDay, ?string $lastLoginAt): ?array;

    /**
     * ログインボーナスの報酬内容を取得（サブクラスで実装必須）
     *
     * @param array $bonusData ログインボーナスデータ
     * @param int $currentDay 現在の受け取り日数
     * @return CustomCollection
     */
    abstract protected function getBonusContents(array $bonusData, int $currentDay): CustomCollection;

    /**
     * ログインボーナス履歴を記録（サブクラスで実装必須）
     * 
     * @param int $sysPlayerId プレイヤーID
     * @param array $bonusData ログインボーナスデータ
     * @param int $currentDay 現在の受け取り日数
     * @param CustomCollection $contents 報酬内容
     * @param string $connectionName シャーディングされたDB接続名
     * @return void
     */
    abstract protected function recordHistory(
        int $sysPlayerId,
        array $bonusData,
        int $currentDay,
        CustomCollection $contents,
        string $connectionName
    ): void;

    /**
     * ループするかどうか
     * 
     * デフォルト: true（通常ログインボーナス、VIPログインボーナス）
     * オーバーライド: カムバックボーナスはfalse
     * 
     * @return bool
     */
    protected function shouldLoop(): bool
    {
        return true; // デフォルトはループする
    }

    /**
     * 休眠時にスキップするかどうか
     * 
     * デフォルト: false（通常ログインボーナス、VIPログインボーナス）
     * オーバーライド: カムバックボーナスはtrue
     * 
     * @return bool
     */
    protected function shouldSkipOnAbsence(): bool
    {
        return false; // デフォルトはスキップしない
    }

    /**
     * 現在の受け取り日数を計算
     * 
     * デフォルト実装: 通常ログインボーナスの動作
     * - ループ有、スキップなし
     * - 前回の受け取り日数 + 1、ループ日数を超えたら1に戻る
     * 
     * @param int $sysPlayerId プレイヤーID
     * @param string|null $lastLoginAt 最終ログイン日時
     * @param string $connectionName シャーディングされたDB接続名
     * @return int 現在の受け取り日数（1から開始）
     */
    protected function calculateCurrentDay(int $sysPlayerId, ?string $lastLoginAt, string $connectionName): int
    {
        // サブクラスで getLastReceivedDay() を実装することを想定
        $lastReceivedDay = $this->getLastReceivedDay($sysPlayerId, $connectionName);

        if ($lastReceivedDay === null) {
            // 初回 = 1日目
            return 1;
        }

        // 次の日に進む
        $nextDay = $lastReceivedDay + 1;

        // ループ処理
        if ($this->shouldLoop()) {
            $loopDays = $this->getLoopDays($sysPlayerId);
            if ($loopDays !== null && $nextDay > $loopDays) {
                return 1; // 1日目に戻る
            }
        }

        return $nextDay;
    }

    /**
     * 前回受け取った日数を取得
     * 
     * サブクラスでオーバーライド可能
     * 
     * @param int $sysPlayerId プレイヤーID
     * @param string $connectionName シャーディングされたDB接続名
     * @return int|null 前回受け取った日数、初回の場合はnull
     */
    protected function getLastReceivedDay(int $sysPlayerId, string $connectionName): ?int
    {
        // デフォルト実装: サブクラスでオーバーライドすることを想定
        return null;
    }

    /**
     * ループ日数を取得
     * 
     * サブクラスでオーバーライド可能
     * 
     * @param int $sysPlayerId プレイヤーID
     * @return int|null ループ日数、ループしない場合はnull
     */
    protected function getLoopDays(int $sysPlayerId): ?int
    {
        // デフォルト実装: サブクラスでオーバーライドすることを想定
        return null;
    }

    /**
     * {@inheritDoc}
     */
    final public function process(int $sysPlayerId, ?string $lastLoginAt, string $connectionName): array
    {
        if (!$this->isEligible($sysPlayerId, $lastLoginAt)) {
            return [];
        }

        // 現在の受け取り日数を計算
        $currentDay = $this->calculateCurrentDay($sysPlayerId, $lastLoginAt, $connectionName);

        // ログインボーナスデータを取得
        $bonusData = $this->getLoginBonusData($sysPlayerId, $currentDay, $lastLoginAt);

        if ($bonusData === null) {
            return [];
        }

        // 配布前のフック
        if (!$this->beforeGrant($sysPlayerId, $bonusData, $currentDay, $connectionName)) {
            return [];
        }

        // 報酬を配布
        $rewards = $this->grantBonus($sysPlayerId, $bonusData, $currentDay, $connectionName);

        // 配布後のフック
        $this->afterGrant($sysPlayerId, $bonusData, $currentDay, $rewards, $connectionName);

        return $rewards;
    }

    /**
     * 配布前のフック（サブクラスでオーバーライド可能）
     * 
     * @param int $sysPlayerId プレイヤーID
     * @param array $bonusData ログインボーナスデータ
     * @param int $currentDay 現在の受け取り日数
     * @param string $connectionName シャーディングされたDB接続名
     * @return bool 配布を続行する場合true、中止する場合false
     */
    protected function beforeGrant(int $sysPlayerId, array $bonusData, int $currentDay, string $connectionName): bool
    {
        return true;
    }

    /**
     * 配布後のフック（サブクラスでオーバーライド可能）
     * 
     * @param int $sysPlayerId プレイヤーID
     * @param array $bonusData ログインボーナスデータ
     * @param int $currentDay 現在の受け取り日数
     * @param array $rewards 配布した報酬
     * @param string $connectionName シャーディングされたDB接続名
     * @return void
     */
    protected function afterGrant(int $sysPlayerId, array $bonusData, int $currentDay, array $rewards, string $connectionName): void
    {
        // デフォルトは何もしない
    }

    /**
     * ログインボーナスを配布
     *
     * @param int $sysPlayerId プレイヤーID
     * @param array $bonusData ログインボーナスデータ
     * @param int $currentDay 現在の受け取り日数
     * @param string $connectionName シャーディングされたDB接続名
     * @return array<ResourceDto> 配布した報酬
     */
    protected function grantBonus(int $sysPlayerId, array $bonusData, int $currentDay, string $connectionName): array
    {
        // 報酬内容を取得
        $contents = $this->getBonusContents($bonusData, $currentDay);

        if ($contents->isEmpty()) {
            return [];
        }

        // Resourceに変換
        $resources = $contents->map(function ($content) {
            return $this->convertToResource($content);
        })->all();

        // ResourceDeliveryServiceで配布
        $this->resourceDeliveryService->addResources($resources);
        $this->resourceDeliveryService->deliver($sysPlayerId);

        // 履歴を記録
        $this->recordHistory($sysPlayerId, $bonusData, $currentDay, $contents, $connectionName);

        return $resources;
    }

    /**
     * ContentをResourceに変換（サブクラスでオーバーライド可能）
     * 
     * @param object $content
     * @return ResourceDto
     */
    protected function convertToResource(object $content): ResourceDto
    {
        $metadata = [];
        if (isset($content->is_paid) && $content->is_paid) {
            $metadata['is_paid'] = true;
        }

        return ResourceDto::fromTypeString(
            typeString: $content->content_type,
            id: $content->content_id,
            amount: $content->content_quantity * $content->amount,
            metadata: empty($metadata) ? null : $metadata,
        );
    }

    /**
     * ゲーム内日付の開始時刻を取得
     *
     * @return CarbonImmutable
     */
    protected function getGameDayStart(): CarbonImmutable
    {
        return ClockUtility::getGameDayStart(ClockUtility::nowToString());
    }
}
