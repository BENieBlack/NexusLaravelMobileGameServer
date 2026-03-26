<?php

namespace App\Models\Trx;

use App\Models\_BaseModel;

/**
 * _BaseTrx
 * 
 * Trxデータベースのモデル基底クラス
 * Unit of Workパターンで管理されるトランザクションデータ
 */
abstract class _BaseTrx extends _BaseModel implements _BaseTrxInterface
{
    /**
     * データベース接続名（trx1, trx2など）
     * サブクラスでオーバーライド可能
     * 
     * @var string
     */
    protected $connection = 'trx';

    /**
     * Unit of Workパターンを使用
     * 
     * @var bool
     */
    protected bool $usesUnitOfWork = true;

    /**
     * @var string DBにSELECTする際に自身を特定できるキー
     * @example trx_playerであれば 'sys_player_id'
     * @example trx_unitであれば 'sys_player_id'
     */
    protected string $selectKey;

    /**
     * @var array 自身のデータ内で一意となるカラム名の配列
     * @example trx_playerであれば ['sys_player_id']
     * @example trx_unitであれば ['id']
     * @example trx_itemであれば ['trx_item_id']
     */
    protected array $uniqueKeys = [];

    /**
     * SELECTキーを取得
     * 
     * @return string
     */
    public function getSelectKey(): string
    {
        return $this->selectKey;
    }

    /**
     * ユニークキーを取得
     * 
     * @return array
     */
    public function getUniqueKeys(): array
    {
        return $this->uniqueKeys;
    }
}
