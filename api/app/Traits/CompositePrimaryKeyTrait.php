<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

/**
 * CompositePrimaryKeyTrait
 *
 * 複合主キーを持つEloquentモデル用のトレイト
 *
 * Laravelは単一主キーを前提としているため、UPDATE時のWHERE句を組み立て直す。
 * 使う側は `$primaryKey` に配列を指定し、`$incrementing = false` にすること。
 *
 * ```php
 * class TrxItem extends _BaseTrx
 * {
 *     use CompositePrimaryKeyTrait;
 *
 *     public $incrementing = false;
 *
 *     protected $primaryKey = ['sys_player_id', 'mst_item_id'];
 * }
 * ```
 */
trait CompositePrimaryKeyTrait
{
    /**
     * 複合主キーを設定
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function setKeysForSaveQuery($query)
    {
        $keys = $this->getCompositeKeyNames();

        if (! is_array($keys)) {
            // 親は受け取った$queryにWHEREを足して同じインスタンスを返すため、
            // ジェネリクスを保つためにこちらで$queryを返す
            parent::setKeysForSaveQuery($query);

            return $query;
        }

        foreach ($keys as $keyName) {
            $query->where($keyName, '=', $this->getKeyForSaveQuery($keyName));
        }

        return $query;
    }

    /**
     * 複合主キーの値を取得
     *
     * @param  string|null  $keyName
     * @return mixed
     */
    protected function getKeyForSaveQuery($keyName = null)
    {
        if (is_null($keyName)) {
            $keyName = $this->getKeyName();
        }

        if (isset($this->original[$keyName])) {
            return $this->original[$keyName];
        }

        return $this->getAttribute($keyName);
    }

    /**
     * 主キー名を取得する
     *
     * Eloquentは `getKeyName()` を string として宣言しているが、
     * このトレイトを使うモデルは配列を返すため、型を明示し直す。
     *
     * @return string|list<string>
     */
    protected function getCompositeKeyNames(): string|array
    {
        /** @var string|list<string> $keys */
        $keys = $this->getKeyName();

        return $keys;
    }
}
