<?php

namespace App\Models\Mst;

/**
 * MstItem Model
 *
 * @property int $deploy_key
 * @property string $id
 * @property string $type
 * @property string $effect
 * @property float $value
 */
class MstItem extends _BaseMst
{
    public $table = 'mst_item';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'deploy_key',
        'id',
        'type',
        'effect',
        'value',
        'is_wallet',
    ];

    /**
     * @var array<string, string>
     */
    /** @var array<string, string> */
    protected $casts = [
        'deploy_key' => 'integer',
        // DBは double。integerにすると小数が落ちる
        'value' => 'float',
        'is_wallet' => 'boolean',
    ];

    public $timestamps = true;

    /**
     * Wallet管理のアイテムかどうか
     *
     * trueなら残高として持ち、trx_item ではなく trx_wallet 系で扱う。
     * 取得単位の有効期限と先入先出の消費が要るものに立てる。
     */
    public function isWallet(): bool
    {
        return (bool) $this->getAttribute('is_wallet');
    }

    /**
     * アイテムタイプを取得
     */
    public function getType(): string
    {
        return $this->getAttribute('type');
    }

    /**
     * アイテム効果を取得
     */
    public function getEffect(): string
    {
        return $this->getAttribute('effect');
    }

    /**
     * アイテム値を取得
     */
    public function getValue(): float
    {
        return (float) $this->getAttribute('value');
    }

    /**
     * レスポンス用配列に変換
     *
     * Note: 主キーが文字列型（semantic ID like "item_gold", "item_exp_potion"）のため、変換不要
     */
    public function toResponseArray(): array
    {
        return parent::toResponseArray();
    }
}
