<?php

namespace App\Models\Trx;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TrxWalletBalance Model
 * 
 * 通貨残高を取得単位で管理するモデル（FIFO方式 + 有効期限管理）
 * PRIMARY KEY: id
 * 
 * FIFO優先順位:
 * 1. expire_at ASC (有効期限が近いものから、NULLは最後)
 * 2. id ASC (古い取得から)
 * 
 * mst_item_id: 通貨アイテムID (string型: "gold", "event_coin"等)
 * current_amount: 現在の残数
 * initial_amount: 取得時の数
 * expire_at: 有効期限 (NULLの場合は無期限)
 */
class TrxWalletBalance extends _BaseTrx
{
    protected $table = 'trx_wallet_balance';

    /**
     * SELECTキー（プレイヤーIDでSELECT）
     * 
     * @var string
     */
    protected string $selectKey = 'sys_player_id';

    /**
     * ユニークキー（IDで一意）
     * 
     * @var array
     */
    protected array $uniqueKeys = ['id'];

    protected $fillable = [
        'sys_player_id',
        'mst_item_id',
        'current_amount',
        'initial_amount',
        'expire_at',
        'is_delete',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'id' => 'integer',
        'sys_player_id' => 'integer',
        'mst_item_id' => 'string',
        'current_amount' => 'integer',
        'initial_amount' => 'integer',
        'expire_at' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    /**
     * trx_playerとのリレーション
     *
     * @return BelongsTo
     */
    public function trxPlayer(): BelongsTo
    {
        return $this->belongsTo(TrxPlayer::class, 'sys_player_id', 'sys_player_id');
    }

    /**
     * レスポンス用配列に変換
     * 
     * データベース層の'id'をAPI層の'trx_wallet_balance_id'に変換
     * 
     * @return array
     */
    public function toResponseArray(): array
    {
        $array = parent::toResponseArray();
        
        if (isset($array['id'])) {
            $array['trx_wallet_balance_id'] = $array['id'];
            unset($array['id']);
        }
        
        return $array;
    }

    // ===== Getter Methods =====

    /**
     * 現在の残高を取得
     * 
     * @return int
     */
    public function getCurrentAmount(): int
    {
        return $this->current_amount;
    }

    // ===== Setter Methods =====

    /**
     * 現在の残高を設定
     * 
     * @param int $currentAmount
     * @return void
     */
    public function setCurrentAmount(int $currentAmount): void
    {
        $this->setAttribute('current_amount', $currentAmount);
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
     * マスターアイテムIDを設定
     *
     * @param string $mstItemId
     * @return void
     */
    public function setMstItemId(string $mstItemId): void
    {
        $this->setAttribute('mst_item_id', $mstItemId);
    }

    /**
     * 初期取得数量を設定
     *
     * @param int $initialAmount
     * @return void
     */
    public function setInitialAmount(int $initialAmount): void
    {
        $this->setAttribute('initial_amount', $initialAmount);
    }

    /**
     * 有効期限を設定
     *
     * @param \Carbon\CarbonImmutable|null $expireAt
     * @return void
     */
    public function setExpireAt(?\Carbon\CarbonImmutable $expireAt): void
    {
        $this->setAttribute('expire_at', $expireAt);
    }
}
