<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * _BaseModel
 * 
 * 全てのモデルの最上位基底クラス
 * 共通の振る舞いとヘルパーメソッドを提供
 */
abstract class _BaseModel extends Model implements _BaseModelInterface
{
    use HasFactory;

    /**
     * データベース接続名
     * サブクラスでオーバーライド必須
     * 
     * @var string
     */
    protected $connection;

    /**
     * モデルがUnit of Workパターンで管理されるかどうか
     * Trx, Logモデルはtrue、Mst, Sysモデルはfalse
     * 
     * @var bool
     */
    protected bool $usesUnitOfWork = false;

    /**
     * Unit of Workパターンを使用するかどうか
     * 
     * @return bool
     */
    public function usesUnitOfWork(): bool
    {
        return $this->usesUnitOfWork;
    }

    /**
     * データベース接続名を取得
     * 
     * @return string
     */
    public function getConnectionName(): string
    {
        return $this->connection;
    }

    /**
     * テーブル名を取得（エイリアス）
     * 
     * @return string
     */
    public function getTableName(): string
    {
        return $this->getTable();
    }

    /**
     * モデルの属性を配列として取得（デバッグ用）
     * 
     * @return array
     */
    public function toDebugArray(): array
    {
        return [
            'table' => $this->getTable(),
            'connection' => $this->getConnectionName(),
            'primaryKey' => $this->getKeyName(),
            'exists' => $this->exists,
            'attributes' => $this->attributes,
            'original' => $this->original,
            'changes' => $this->getChanges(),
            'dirty' => $this->getDirty(),
        ];
    }

    /**
     * モデルが新規作成かどうか（INSERTが必要か）
     * 
     * @return bool
     */
    public function isNew(): bool
    {
        return !$this->exists;
    }

    /**
     * モデルが更新対象かどうか（UPDATEが必要か）
     * 
     * @return bool
     */
    public function needsUpdate(): bool
    {
        return $this->exists && $this->isDirty();
    }

    /**
     * モデルに変更があるかどうか（INSERT or UPDATEが必要か）
     * 
     * @return bool
     */
    public function needsSave(): bool
    {
        return $this->isNew() || $this->needsUpdate();
    }

    /**
     * APIレスポンス用の配列に変換
     * 
     * @return array<string, mixed>
     */
    public function toResponseArray(): array
    {
        $array = $this->toArray();
        
        // 日付フィールドをISO8601形式に変換
        foreach ($this->getDates() as $dateField) {
            if (isset($array[$dateField]) && $this->{$dateField} instanceof \DateTimeInterface) {
                $array[$dateField] = $this->{$dateField}->toIso8601String();
            }
        }
        
        // クライアントに渡さない内部情報を除外
        unset($array['sys_player_id']);
        unset($array['uuid']);
        unset($array['created_at']);
        unset($array['updated_at']);
        
        return $array;
    }
}
