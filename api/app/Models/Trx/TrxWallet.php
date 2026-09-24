<?php

namespace App\Models\Trx;

use App\Traits\CompositePrimaryKeyTrait;
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
 * free_amount: 無償通貨数
 * paid_amount: 有償通貨数
 *
 * FIFO管理・有効期限管理が必要な通貨を管理
 * trx_wallet_balanceテーブルで取得単位の詳細管理を行う
 *
 * @property int $free_amount
 * @property int $paid_amount
 */
class TrxWallet extends _BaseTrx
{
    use CompositePrimaryKeyTrait;

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
     */
    /** @var list<string> */
    protected array $selectKeys = ['sys_player_id'];

    /**
     * ユニークキー（複合キーで一意）
     */

    /** @var list<string> */
    protected $fillable = [
        'sys_player_id',
        'mst_item_id',
        'free_amount',
        'paid_amount',
        'is_delete',
        'created_at',
        'updated_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'sys_player_id' => 'integer',
        'mst_item_id' => 'string',
        'free_amount' => 'integer',
        'paid_amount' => 'integer',
    ];

    /**
     * trx_playerとのリレーション
     */
    /**
     * @return BelongsTo<TrxPlayer, $this>
     */
    public function trxPlayer(): BelongsTo
    {
        return $this->belongsTo(TrxPlayer::class, 'sys_player_id', 'sys_player_id');
    }

    /**
     * レスポンス用配列に変換
     *
     * Note: 複合主キー(sys_player_id, mst_item_id)のため、idフィールドは存在しない
     */
    public function toResponseArray(): array
    {
        return parent::toResponseArray();
    }

    // ===== Getter Methods =====

    /**
     * 無償通貨の残高を取得
     */
    public function getFreeAmount(): int
    {
        return $this->free_amount;
    }

    /**
     * 有償通貨の残高を取得
     */
    public function getPaidAmount(): int
    {
        return $this->paid_amount;
    }

    /**
     * 合計残高を取得（無償 + 有償）
     */
    public function getTotalAmount(): int
    {
        return $this->getFreeAmount() + $this->getPaidAmount();
    }

    // ===== Setter Methods =====

    /**
     * 無償通貨の残高を設定
     */
    public function setFreeAmount(int $freeAmount): void
    {
        $this->setAttribute('free_amount', $freeAmount);
    }

    /**
     * 有償通貨の残高を設定
     */
    public function setPaidAmount(int $paidAmount): void
    {
        $this->setAttribute('paid_amount', $paidAmount);
    }

    /**
     * システムプレイヤーIDを設定
     */
    public function setSysPlayerId(int $sysPlayerId): void
    {
        $this->setAttribute('sys_player_id', $sysPlayerId);
    }

    /**
     * マスターアイテムIDを設定
     */
    public function setMstItemId(string $mstItemId): void
    {
        $this->setAttribute('mst_item_id', $mstItemId);
    }
}
