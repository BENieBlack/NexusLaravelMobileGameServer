<?php

namespace Nexus\Core\Models;

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
     * タイムスタンプフィールドの自動キャストを無効化
     * パフォーマンス最適化のため、DB取得時はstring型のまま保持し、
     * toResponseArray()で必要に応じてCarbonにキャストしてISO8601形式で返す
     * 
     * @var array<string, string>
     */
    protected $casts = [
        // デフォルトのcreated_at, updated_atのdatetime自動キャストを無効化
        // サブクラスで必要に応じて個別にキャスト定義可能
    ];

    /**
     * Eloquentのデフォルトタイムスタンプ自動キャストを無効化
     * 
     * @return bool
     */
    public function usesTimestamps()
    {
        // タイムスタンプ機能自体は有効だが、Carbonへの自動キャストは行わない
        return parent::usesTimestamps();
    }

    /**
     * 日付属性をCarbon型として取得
     * 
     * パフォーマンス最適化のため、DB取得時はstring型で保持し、
     * このメソッドで必要に応じてCarbon型に変換する
     * 
     * @param string $attribute 属性名（例: 'created_at', 'start_at'）
     * @return \Carbon\Carbon|null
     */
    protected function getDateAttribute(string $attribute): ?\Carbon\Carbon
    {
        $value = $this->getAttribute($attribute);
        
        if ($value === null) {
            return null;
        }
        
        if ($value instanceof \Carbon\Carbon) {
            return $value;
        }
        
        if ($value instanceof \DateTimeInterface) {
            return \Carbon\Carbon::instance($value);
        }
        
        if (is_string($value)) {
            return \Carbon\Carbon::parse($value);
        }
        
        return null;
    }

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
     * パフォーマンス最適化のため、DB取得時はstring型で保持し、
     * レスポンス生成時のみCarbonにパースしてISO8601形式に変換する
     * 
     * @return array<string, mixed>
     */
    public function toResponseArray(): array
    {
        $array = $this->toArray();
        
        // 日付フィールドをISO8601形式に変換
        // DB取得時はstring型なので、ここで明示的にCarbonにパースする
        foreach ($this->getDates() as $dateField) {
            if (isset($array[$dateField]) && is_string($array[$dateField])) {
                try {
                    $carbon = \Carbon\Carbon::parse($array[$dateField]);
                    $array[$dateField] = $carbon->toIso8601String();
                } catch (\Exception $e) {
                    // パース失敗時は元の値をそのまま使用
                    // エラーログは出さずに続行（DBから取得した値は通常パース可能）
                }
            } elseif (isset($array[$dateField]) && $array[$dateField] instanceof \DateTimeInterface) {
                // 既にCarbon/DateTime型の場合（後方互換性のため）
                $array[$dateField] = $array[$dateField]->toIso8601String();
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
