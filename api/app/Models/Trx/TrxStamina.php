<?php

namespace App\Models\Trx;

use App\Models\Sys\SysPlayer;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TrxStamina Model
 * 
 * プレイヤーのスタミナ管理
 * 
 * @property int $id
 * @property int $sys_player_id
 * @property int $current_stamina 現在のスタミナ（通常枠）
 * @property int $overflow_stamina オーバーフロースタミナ（最大値超過分）
 * @property float $recovery_rate_multiplier 回復速度倍率（VIP特典等）
 * @property \Carbon\CarbonImmutable $last_recovery_at 最後の回復計算時刻
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 */
class TrxStamina extends _BaseTrx
{
    protected $table = 'trx_stamina';

    /**
     * @var string テーブルのPK
     */
    protected $primaryKey = 'id';

    /**
     * @var bool 自動インクリメント有効
     */
    public $incrementing = true;

    /**
     * @var string プライマリキーの型
     */
    protected $keyType = 'int';

    /**
     * SELECTキー（sys_player_idで検索）
     * 
     * @var string
     */
    protected string $selectKey = 'sys_player_id';

    /**
     * ユニークキー（sys_player_idで一意）
     * 
     * @var array
     */
    protected array $uniqueKeys = ['sys_player_id'];

    protected $fillable = [
        'sys_player_id',
        'current_stamina',
        'overflow_stamina',
        'recovery_rate_multiplier',
        'last_recovery_at',
        'is_delete',
    ];

    protected $casts = [
        'sys_player_id' => 'integer',
        'current_stamina' => 'integer',
        'overflow_stamina' => 'integer',
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
     * 現在スタミナを取得
     *
     * @return int
     */
    public function getCurrentStamina(): int
    {
        return $this->getAttribute('current_stamina');
    }

    /**
     * オーバーフロースタミナを取得
     *
     * @return int
     */
    public function getOverflowStamina(): int
    {
        return $this->getAttribute('overflow_stamina');
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
     * 合計スタミナを取得（通常枠 + オーバーフロー枠）
     * 
     * @return int
     */
    public function getTotalStamina(): int
    {
        return $this->getCurrentStamina() + $this->getOverflowStamina();
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
        return $this->getTotalStamina() >= $required;
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
     * オーバーフロースタミナを設定
     *
     * @param int $overflowStamina
     * @return void
     */
    public function setOverflowStamina(int $overflowStamina): void
    {
        $this->setAttribute('overflow_stamina', $overflowStamina);
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
     * データベース層の'id'をAPI層の'trx_stamina_id'に変換
     * 
     * @return array
     */
    public function toResponseArray(): array
    {
        $array = parent::toResponseArray();
        
        if (isset($array['id'])) {
            $array['trx_stamina_id'] = $array['id'];
            unset($array['id']);
        }
        
        return $array;
    }
}
