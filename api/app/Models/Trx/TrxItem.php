<?php

namespace App\Models\Trx;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TrxItem Model
 * 
 * プレイヤーが所持するアイテムを管理するモデル
 * PRIMARY KEY: (sys_player_id, mst_item_id)
 */
class TrxItem extends _BaseTrx
{
    protected $table = 'trx_item';

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
     * ユニークキー（アイテムは複合キーで一意）
     * 
     * @var array
     */
    protected array $uniqueKeys = ['sys_player_id', 'mst_item_id'];

    protected $fillable = [
        'sys_player_id',
        'mst_item_id',
        'free_amount',
        'paid_amount',
        'is_delete',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'sys_player_id' => 'integer',
        'free_amount' => 'integer',
        'paid_amount' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 複合主キーを設定
     * 
     * @param  array  $ids
     * @return $this
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
     * @param  string  $keyName
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
     * APIレスポンス用の配列に変換
     * TrxItemは複合主キー(sys_player_id, mst_item_id)のため、idフィールドは存在しない
     * 
     * @return array<string, mixed>
     */
    public function toResponseArray(): array
    {
        return parent::toResponseArray();
    }

    // ===== Getter Methods =====

    /**
     * プレイヤーIDを取得
     * 
     * @return int
     */
    public function getSysPlayerId(): int
    {
        return $this->getAttribute('sys_player_id');
    }

    /**
     * マスターアイテムIDを取得
     * 
     * @return string
     */
    public function getMstItemId(): string
    {
        return $this->getAttribute('mst_item_id');
    }

    /**
     * 無償アイテムの所持数を取得
     * 
     * @return int
     */
    public function getFreeAmount(): int
    {
        return $this->getAttribute('free_amount');
    }

    /**
     * 有償アイテムの所持数を取得
     * 
     * @return int
     */
    public function getPaidAmount(): int
    {
        return $this->getAttribute('paid_amount');
    }

    /**
     * 合計アイテム数を取得（無償 + 有償）
     * 
     * @return int
     */
    public function getTotalAmount(): int
    {
        return $this->getFreeAmount() + $this->getPaidAmount();
    }

    /**
     * 削除フラグを取得
     * 
     * @return bool
     */
    public function getIsDelete(): bool
    {
        return (bool)$this->getAttribute('is_delete');
    }

    // ===== Setter Methods =====

    /**
     * プレイヤーIDを設定
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
     * 無償アイテムの所持数を設定
     * 
     * @param int $freeAmount
     * @return void
     */
    public function setFreeAmount(int $freeAmount): void
    {
        $this->setAttribute('free_amount', $freeAmount);
    }

    /**
     * 有償アイテムの所持数を設定
     * 
     * @param int $paidAmount
     * @return void
     */
    public function setPaidAmount(int $paidAmount): void
    {
        $this->setAttribute('paid_amount', $paidAmount);
    }

    /**
     * 削除フラグを設定
     * 
     * @param bool $isDelete
     * @return void
     */
    public function setIsDelete(bool $isDelete): void
    {
        $this->setAttribute('is_delete', $isDelete);
    }
}

