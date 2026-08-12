<?php

namespace NexusVip\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * VIPレベルアップイベント
 *
 * VIPレベルがアップした時に発火される
 * このイベントをリスンして報酬付与処理を実装する
 */
class VipLevelUpEvent
{
    use Dispatchable, SerializesModels;

    /**
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  int  $beforeLevel  変更前VIPレベル
     * @param  int  $afterLevel  変更後VIPレベル
     * @param  array  $rewards  レベルアップ報酬リスト（VipReward[]のarray形式）
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
