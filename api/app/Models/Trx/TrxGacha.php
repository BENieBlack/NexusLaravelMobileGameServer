<?php

namespace App\Models\Trx;

use App\Traits\CompositePrimaryKeyTrait;

/**
 * TrxGacha Model
 *
 * ガチャプレイヤー進行状況モデル
 *
 * @property int $sys_player_id
 * @property string $mst_gacha_id
 * @property int $current_step
 * @property int $daily_draw_count
 * @property string|null $daily_reset_at
 * @property int $total_draw_count
 * @property string|null $total_reset_at
 * @property bool $is_delete
 */
class TrxGacha extends _BaseTrx
{
    use CompositePrimaryKeyTrait;

    protected $table = 'trx_gacha';

    /**
     * 採番idは持たず、業務上の一意をそのまま主キーにする
     */
    public $incrementing = false;

    protected $keyType = 'string';

    protected $primaryKey = ['sys_player_id', 'mst_gacha_id'];

    /** @var list<string> */
    protected array $selectKeys = ['sys_player_id'];

    /** @var list<string> */
    protected $fillable = [
        'sys_player_id',
        'mst_gacha_id',
        'current_step',
        'daily_draw_count',
        'daily_reset_at',
        'total_draw_count',
        'total_reset_at',
        'is_delete',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'sys_player_id' => 'integer',
        'current_step' => 'integer',
        'daily_draw_count' => 'integer',
        'total_draw_count' => 'integer',
        'is_delete' => 'boolean',
    ];

    public $timestamps = true;

    public function getSysPlayerId(): int
    {
        return $this->getAttribute('sys_player_id');
    }

    public function getMstGachaId(): string
    {
        return $this->getAttribute('mst_gacha_id');
    }

    public function getCurrentStep(): int
    {
        return $this->getAttribute('current_step');
    }

    public function getDailyDrawCount(): int
    {
        return $this->getAttribute('daily_draw_count');
    }

    public function getTotalDrawCount(): int
    {
        return $this->getAttribute('total_draw_count');
    }

    /**
     * current_step を設定
     */
    public function setCurrentStep(int $currentStep): void
    {
        $this->setAttribute('current_step', $currentStep);
    }

    /**
     * daily_draw_count を設定
     */
    public function setDailyDrawCount(int $dailyDrawCount): void
    {
        $this->setAttribute('daily_draw_count', $dailyDrawCount);
    }

    /**
     * daily_reset_at を取得
     */
    public function getDailyResetAt(): ?string
    {
        return $this->getAttribute('daily_reset_at');
    }

    /**
     * daily_reset_at を設定
     */
    public function setDailyResetAt(?string $dailyResetAt): void
    {
        $this->setAttribute('daily_reset_at', $dailyResetAt);
    }

    /**
     * total_draw_count を設定
     */
    public function setTotalDrawCount(int $totalDrawCount): void
    {
        $this->setAttribute('total_draw_count', $totalDrawCount);
    }

    /**
     * total_reset_at を取得
     */
    public function getTotalResetAt(): ?string
    {
        return $this->getAttribute('total_reset_at');
    }

    /**
     * total_reset_at を設定
     */
    public function setTotalResetAt(?string $totalResetAt): void
    {
        $this->setAttribute('total_reset_at', $totalResetAt);
    }
}
