<?php

namespace App\Models\Mst;

/**
 * MstConfig Model
 *
 * 設定マスター（キーと値の 1 行 1 設定）。
 * 値は文字列で持ち、型は value_type で表す。型の検査と解釈は MstConfigService が行う。
 * 全行がクライアントに配信されるので、秘密情報は入れない。
 *
 * @property int $deploy_key
 * @property string $id
 * @property string $value
 * @property string $value_type
 * @property string $description
 */
class MstConfig extends _BaseMst
{
    public $table = 'mst_config';

    // 主キーは人が決める文字列（AUTO_INCREMENT ではない）
    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'deploy_key',
        'id',
        'value',
        'value_type',
        'description',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'deploy_key' => 'integer',
    ];

    public $timestamps = true;

    /**
     * 設定値（文字列のまま）を取得
     */
    public function getValue(): string
    {
        return (string) $this->getAttribute('value');
    }

    /**
     * 値の型（int / float / bool / string）を取得
     */
    public function getValueType(): string
    {
        return (string) $this->getAttribute('value_type');
    }
}
