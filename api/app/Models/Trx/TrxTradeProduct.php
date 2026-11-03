<?php

namespace App\Models\Trx;

use App\Traits\CompositePrimaryKeyTrait;

/**
 * TrxTradeProduct Model
 *
 * 交換所の商品ごとの交換回数
 *
 * trade_count は last_traded_at が属する周期での回数。
 * 周期が変わったかどうかは NexusTrade\Services\TradeService が判定する。
 *
 * @property int $sys_player_id
 * @property string $mst_trade_product_id
 * @property int $trade_count
 * @property string $last_traded_at
 * @property bool $is_delete
 * @property string $created_at
 * @property string $updated_at
 */
class TrxTradeProduct extends _BaseTrx
{
    use CompositePrimaryKeyTrait;

    protected $table = 'trx_trade_product';

    /**
     * 採番idは持たず、業務上の一意をそのまま主キーにする
     */
    public $incrementing = false;

    protected $keyType = 'string';

    protected $primaryKey = ['sys_player_id', 'mst_trade_product_id'];

    /**
     * SELECTキー（プレイヤーIDでSELECT）
     *
     * @var list<string>
     */
    protected array $selectKeys = ['sys_player_id'];

    /** @var list<string> */
    protected $fillable = [
        'sys_player_id',
        'mst_trade_product_id',
        'trade_count',
        'last_traded_at',
        'is_delete',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'sys_player_id' => 'integer',
        'trade_count' => 'integer',
        'is_delete' => 'boolean',
    ];

    public function getSysPlayerId(): int
    {
        return (int) $this->getAttribute('sys_player_id');
    }

    public function getMstTradeProductId(): string
    {
        return (string) $this->getAttribute('mst_trade_product_id');
    }

    public function getTradeCount(): int
    {
        return (int) $this->getAttribute('trade_count');
    }

    public function setTradeCount(int $tradeCount): void
    {
        $this->setAttribute('trade_count', $tradeCount);
    }

    /**
     * 最後に交換した日時（Y-m-d H:i:s形式）
     */
    public function getLastTradedAt(): string
    {
        return (string) $this->getDateAttributeString('last_traded_at');
    }

    public function setLastTradedAt(string $lastTradedAt): void
    {
        $this->setAttribute('last_traded_at', $lastTradedAt);
    }

    public function getIsDelete(): bool
    {
        return (bool) $this->getAttribute('is_delete');
    }
}
