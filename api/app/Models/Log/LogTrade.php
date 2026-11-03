<?php

namespace App\Models\Log;

/**
 * LogTrade Model
 *
 * 交換所で交換した記録（1回の交換操作につき1行）
 *
 * @property int $id
 * @property string $unique_request_id
 * @property int $sys_player_id
 * @property string $mst_trade_product_id
 * @property int $quantity
 * @property string $cost_type
 * @property string|null $cost_mst_id
 * @property int $cost_amount
 * @property string $system_at
 * @property string $created_at
 */
class LogTrade extends _BaseLog
{
    protected $table = 'log_action_trade';

    /**
     * updated_at カラムなし（created_at はDBの既定値で入る）
     */
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'unique_request_id',
        'sys_player_id',
        'mst_trade_product_id',
        'quantity',
        'cost_type',
        'cost_mst_id',
        'cost_amount',
        'system_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'id' => 'integer',
        'sys_player_id' => 'integer',
        'quantity' => 'integer',
        'cost_amount' => 'integer',
    ];

    public function getSysPlayerId(): int
    {
        return (int) $this->getAttribute('sys_player_id');
    }

    public function getMstTradeProductId(): string
    {
        return (string) $this->getAttribute('mst_trade_product_id');
    }

    public function getQuantity(): int
    {
        return (int) $this->getAttribute('quantity');
    }

    public function getCostType(): string
    {
        return (string) $this->getAttribute('cost_type');
    }

    public function getCostMstId(): ?string
    {
        $costMstId = $this->getAttribute('cost_mst_id');

        return $costMstId === null ? null : (string) $costMstId;
    }

    public function getCostAmount(): int
    {
        return (int) $this->getAttribute('cost_amount');
    }
}
