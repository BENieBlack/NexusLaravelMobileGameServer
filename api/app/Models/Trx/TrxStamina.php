<?php

namespace App\Models\Trx;

use App\Models\Sys\SysPlayer;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TrxStamina Model
 * 
 * プレイヤーのスタミナ管理（タイプ別）
 * 
 * PRIMARY KEY: (sys_player_id, type)
 * 
 * @property int $sys_player_id
 * @property string $type スタミナタイプ（normal, raid, pvp, event等）
 * @property int $current_stamina 現在のスタミナ
 * @property float $recovery_rate_multiplier 回復速度倍率（VIP特典等）
 * @property \Carbon\CarbonImmutable $last_recovery_at 最後の回復計算時刻
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 */
class TrxStamina extends _BaseTrx
{
    protected $table = 'trx_stamina';

    /**
     * @var array<string> 複合主キー
     */
    protected $primaryKey = ['sys_player_id', 'type'];

    /**
     * @var bool 自動インクリメント無効（複合主キー）
     */
    public $incrementing = false;

    /**
     * @var string プライマリキーの型
     */
    protected $keyType = 'string';

    /**
     * SELECTキー（sys_player_idで検索）
     * 
     * @var string
     */
    protected string $selectKey = 'sys_player_id';

    /**
     * ユニークキー（sys_player_id + typeで一意）
     * 
     * @var array<array<string>>
     */
    protected array $uniqueKeys = [['sys_player_id', 'type']];

    protected $fillable = [
        'sys_player_id',
        'type',
        'current_stamina',
        'recovery_rate_multiplier',
        'last_recovery_at',
        'is_delete',
    ];

    protected $casts = [
        'sys_player_id' => 'integer',
        'type' => 'string',
        'current_stamina' => 'integer',
        'recovery_rate_multiplier' => 'decimal:2',
        'last_recovery_at' => 'datetime:Y-m-d H:i:s',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * sys_playerとのリレーション
     *
     * @return BelongsTo
     */
    public function sysPlayer(): BelongsTo
    {
        return $this->belongsTo(SysPlayer::class, 'sys_player_id', 'id');
    }

    /**
     * スタミナタイプを取得
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->getAttribute('type');
    }

    /**
     * 現在スタミナを取得
     *
     * @return int
     */
    public function getCurrentStamina(): int
    {
        return $this->getAttribute('current_stamina');
    }

    /**
     * 回復速度倍率を取得
     *
     * @return float
     */
    public function getRecoveryRateMultiplier(): float
    {
        return (float)$this->getAttribute('recovery_rate_multiplier');
    }

    /**
     * 通常スタミナ枠が上限に達しているか
     * 
     * @param int $maxStamina プレイヤーの最大スタミナ（レベル依存）
     * @return bool
     */
    public function isCurrentStaminaFull(int $maxStamina): bool
    {
        return $this->getCurrentStamina() >= $maxStamina;
    }

    /**
     * スタミナが足りているか
     * 
     * @param int $required 必要なスタミナ量
     * @return bool
     */
    public function hasEnoughStamina(int $required): bool
    {
        return $this->getCurrentStamina() >= $required;
    }

    /**
     * スタミナタイプを設定
     *
     * @param string $type
     * @return void
     */
    public function setType(string $type): void
    {
        $this->setAttribute('type', $type);
    }

    /**
     * システムプレイヤーIDを設定
     *
     * @param int $sysPlayerId
     * @return void
     */
    public function setSysPlayerId(int $sysPlayerId): void
    {
        $this->setAttribute('sys_player_id', $sysPlayerId);
    }

    /**
     * 現在スタミナを設定
     *
     * @param int $currentStamina
     * @return void
     */
    public function setCurrentStamina(int $currentStamina): void
    {
        $this->setAttribute('current_stamina', $currentStamina);
    }

    /**
     * 回復速度倍率を設定
     *
     * @param float $recoveryRateMultiplier
     * @return void
     */
    public function setRecoveryRateMultiplier(float $recoveryRateMultiplier): void
    {
        $this->setAttribute('recovery_rate_multiplier', $recoveryRateMultiplier);
    }

    /**
     * 最後の回復計算時刻を設定
     *
     * @param \Carbon\Carbon $lastRecoveryAt
     * @return void
     */
    public function setLastRecoveryAt(\Carbon\Carbon $lastRecoveryAt): void
    {
        $this->setAttribute('last_recovery_at', $lastRecoveryAt);
    }

    /**
     * レスポンス用配列に変換
     * 
     * @return array
     */
    public function toResponseArray(): array
    {
        return parent::toResponseArray();
    }
}
