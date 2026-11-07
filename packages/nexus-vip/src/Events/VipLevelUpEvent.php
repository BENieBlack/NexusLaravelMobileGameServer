<?php

namespace NexusVip\Events;

/**
 * VIPレベルアップイベント
 *
 * VIPレベルがアップした時に発火される
 * このイベントをリスンして報酬付与処理を実装する
 *
 * 発行は注入した Dispatcher で行う（静的な dispatch() は持たない。laravel/framework に依存しないため）
 */
class VipLevelUpEvent
{
    /**
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  int  $beforeLevel  変更前VIPレベル
     * @param  int  $afterLevel  変更後VIPレベル
     * @param  array<int, array<string, mixed>>  $rewards  レベルアップ報酬リスト（VipReward[]のarray形式）
     */
    public function __construct(
        public readonly int $sysPlayerId,
        public readonly int $beforeLevel,
        public readonly int $afterLevel,
        public readonly array $rewards = [],
    ) {}

    /**
     * 複数レベルアップした場合かチェック
     */
    public function isMultipleLevelUp(): bool
    {
        return ($this->afterLevel - $this->beforeLevel) > 1;
    }

    /**
     * レベルアップ幅を取得
     */
    public function getLevelUpCount(): int
    {
        return $this->afterLevel - $this->beforeLevel;
    }
}
