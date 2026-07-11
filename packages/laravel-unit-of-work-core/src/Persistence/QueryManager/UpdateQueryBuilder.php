<?php

namespace LaravelUnitOfWork\Persistence\QueryManager;

use Illuminate\Database\Eloquent\Model;

/**
 * UpdateQueryBuilder
 * 
 * UPDATE文の構築を担当するクラス
 */
class UpdateQueryBuilder
{
    /**
     * 相対的な更新を含むUPDATE文を構築
     *
     * @param string $table テーブル名
     * @param array $data 通常の更新データ
     * @param array $relativeChanges 相対的な変更データ
     * @param array $where WHERE条件
     * @return array ['sql' => string, 'bindings' => array]
     */
    public function buildRelativeUpdate(string $table, array $data, array $relativeChanges, array $where): array
    {
        $updateClauses = [];
        $bindings = [];
        
        // 通常の更新データ（相対的な変更があるカラムを除外）
        foreach ($data as $column => $value) {
            if (!isset($relativeChanges[$column])) {
                $updateClauses[] = "`{$column}` = ?";
                $bindings[] = $value;
            }
        }
        
        // 相対的な変更を追加
        foreach ($relativeChanges as $column => $change) {
            if ($change !== 0) {
                $operator = $change >= 0 ? '+' : '-';
                $absValue = abs($change);
                $updateClauses[] = "`{$column}` = `{$column}` {$operator} {$absValue}";
            }
        }
        
        // WHERE句を構築
        $wheres = [];
        foreach ($where as $col => $val) {
            $wheres[] = "`{$col}` = ?";
            $bindings[] = $val;
        }
        $whereClause = implode(' and ', $wheres);
        
        // SQL文を構築
        $columns = implode(', ', $updateClauses);
        $sql = "update `{$table}` set {$columns} where ({$whereClause})";
        
        return [
            'sql' => $sql,
            'bindings' => $bindings,
        ];
    }
    
    /**
     * WHERE条件を構築
     * プライマリキーを使用（複合主キーにも対応）
     *
     * @param Model $model
     * @return array
     */
    public function buildWhereCondition(Model $model): array
    {
        $primaryKey = $model->getKeyName();
        
        // 複合主キーの場合
        if (is_array($primaryKey)) {
            $where = [];
            foreach ($primaryKey as $key) {
                $where[$key] = $model->getAttribute($key);
            }
            return $where;
        }
        
        // 単一主キーの場合
        return [$primaryKey => $model->getKey()];
    }
}
