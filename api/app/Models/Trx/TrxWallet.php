<?php

namespace App\Models\Trx;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TrxWallet Model
 * 
 * 汎用通貨現在値を管理するモデル
 * PRIMARY KEY: (sys_player_id, mst_item_id)
 * 
 * Gold, EventCoin, RaidMedal, PvPPoint, GvGPoint等を統合管理
 * 
 * mst_item_id: 通貨アイテムID (string型: "gold", "event_coin"等)
 * amount: 現在の残高
 */
class TrxWallet extends _BaseTrx
{
    protected $table = 'trx_wallet';

    /**
     * 複合主キーのため、auto incrementを無効化
     */
    public $incrementing = false;

    /**
     * 複合主キーの指定
     */
    protected $primaryKey = ['sys_player_id', 'mst_item_id'];

    /**
     * 主キーの型
     */
    protected $keyType = 'string';

    /**
     * SELECTキー（プレイヤーIDでSELECT）
     * 
     * @var string
     */
    protected string $selectKey = 'sys_player_id';

    /**
     * ユニークキー（複合キーで一意）
     * 
     * @var array
     */
    protected array $uniqueKeys = ['sys_player_id', 'mst_item_id'];

    protected $fillable = [
        'sys_player_id',
        'mst_item_id',
        'amount',
        'is_delete',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'sys_player_id' => 'integer',
        'mst_item_id' => 'string',
        'amount' => 'integer',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    /**
     * 複合主キーを設定
     * 
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function setKeysForSaveQuery($query)
    {
        $keys = $this->getKeyName();
        if (!is_array($keys)) {
            return parent::setKeysForSaveQuery($query);
        }

        foreach ($keys as $keyName) {
            $query->where($keyName, '=', $this->getKeyForSaveQuery($keyName));
        }

        return $query;
    }

    /**
     * 複合主キーの値を取得
     * 
     * @param  string|null  $keyName
     * @return mixed
     */
    protected function getKeyForSaveQuery($keyName = null)
    {
        if (is_null($keyName)) {
            $keyName = $this->getKeyName();
        }

        if (isset($this->original[$keyName])) {
            return $this->original[$keyName];
        }

        return $this->getAttribute($keyName);
    }

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
     * Note: 複合主キー(sys_player_id, mst_item_id)のため、idフィールドは存在しない
     * 
     * @return array
     */
    public function toResponseArray(): array
    {
        return parent::toResponseArray();
    }

    // ===== Getter Methods =====

    /**
     * 通貨の残高を取得
     * 
     * @return int
     */
    public function getAmount(): int
    {
        return $this->amount;
    }

    // ===== Setter Methods =====

    /**
     * 通貨の残高を設定
     * 
     * @param int $amount
     * @return void
     */
    public function setAmount(int $amount): void
    {
        $this->setAttribute('amount', $amount);
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
}
