<?php

namespace App\Models\Trx;

use App\Traits\CompositePrimaryKeyTrait;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TrxItem Model
 *
 * プレイヤーが所持するアイテムを管理するモデル
 * PRIMARY KEY: (sys_player_id, mst_item_id)
 */
class TrxItem extends _BaseTrx
{
    use CompositePrimaryKeyTrait;

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
     */
    protected string $selectKey = 'sys_player_id';

    /**
     * ユニークキー（アイテムは複合キーで一意）
     */
    /** @var list<string> */
    protected array $uniqueKeys = ['sys_player_id', 'mst_item_id'];

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
     */
    public function getSysPlayerId(): int
    {
        return $this->getAttribute('sys_player_id');
    }

    /**
     * マスターアイテムIDを取得
     */
    public function getMstItemId(): string
    {
        return $this->getAttribute('mst_item_id');
    }

    /**
     * 無償アイテムの所持数を取得
     */
    public function getFreeAmount(): int
    {
        return $this->getAttribute('free_amount');
    }

    /**
     * 有償アイテムの所持数を取得
     */
    public function getPaidAmount(): int
    {
        return $this->getAttribute('paid_amount');
    }

    /**
     * 合計アイテム数を取得（無償 + 有償）
     */
    public function getTotalAmount(): int
    {
        return $this->getFreeAmount() + $this->getPaidAmount();
    }

    /**
     * 削除フラグを取得
     */
    public function getIsDelete(): bool
    {
        return (bool) $this->getAttribute('is_delete');
    }

    // ===== Setter Methods =====

    /**
     * プレイヤーIDを設定
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

    /**
     * 無償アイテムの所持数を設定
     */
    public function setFreeAmount(int $freeAmount): void
    {
        $this->setAttribute('free_amount', $freeAmount);
    }

    /**
     * 有償アイテムの所持数を設定
     */
    public function setPaidAmount(int $paidAmount): void
    {
        $this->setAttribute('paid_amount', $paidAmount);
    }

    /**
     * 削除フラグを設定
     */
    public function setIsDelete(bool $isDelete): void
    {
        $this->setAttribute('is_delete', $isDelete);
    }
}
