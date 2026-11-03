<?php

namespace App\Models\Mst;

use App\Traits\CompositePrimaryKeyTrait;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MstTradeL10n Model
 *
 * 交換所の表示名（多言語）
 *
 * @property int $deploy_key
 * @property string $mst_trade_id
 * @property string $language
 * @property string $name
 * @property string $created_at
 * @property string $updated_at
 */
class MstTradeL10n extends _BaseMst
{
    use CompositePrimaryKeyTrait;

    public $table = 'mst_trade__l10n';

    public $incrementing = false;

    protected $keyType = 'string';

    /** テーブル定義どおりの複合主キー */
    protected $primaryKey = ['mst_trade_id', 'language'];

    /** @var list<string> */
    protected $fillable = [
        'deploy_key',
        'mst_trade_id',
        'language',
        'name',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'deploy_key' => 'integer',
    ];

    public $timestamps = true;

    /**
     * @return BelongsTo<MstTrade, $this>
     */
    public function trade(): BelongsTo
    {
        return $this->belongsTo(MstTrade::class, 'mst_trade_id', 'id');
    }

    public function getMstTradeId(): string
    {
        return (string) $this->getAttribute('mst_trade_id');
    }

    public function getLanguage(): string
    {
        return (string) $this->getAttribute('language');
    }

    public function getName(): string
    {
        return (string) $this->getAttribute('name');
    }
}
