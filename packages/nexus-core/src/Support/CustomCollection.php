<?php

namespace Nexus\Core\Support;

use Illuminate\Support\Collection;

/**
 * CustomCollection
 *
 * Laravelの標準Collectionをラップし、パフォーマンス最適化を行うクラス
 * 
 * 目的：
 * - Collectionのfilter/where/reject等は内部的にインスタンスコピーを生成する
 * - 大量データを扱う場合、このコピーがメモリとパフォーマンスのボトルネックになる
 * - array_filter等のPHP標準関数を使い、インスタンスコピーを避けて直接配列を操作する
 * 
 * オーバーライド対象メソッド：
 * - filter(): array_filterを使用
 * - where(): array_filterを使用
 * - whereIn(): array_filterを使用
 * - whereNotIn(): array_filterを使用
 * - reject(): array_filterを使用
 * - first(): foreach早期リターンを使用
 * - firstWhere(): foreach早期リターンを使用
 * 
 * new static() でインスタンスを作り直すため、コンストラクタのシグネチャが
 * サブクラスでも変わらないことを前提にする。
 * 
 * @template TKey of array-key
 * @template TValue
 * @extends Collection<TKey, TValue>
 * @phpstan-consistent-constructor
 */
class CustomCollection extends Collection
{
    /**
     * コレクションの各アイテムに対してフィルタを実行
     * 
     * @param callable|null $callback
     * @return static
     */
    public function filter(?callable $callback = null)
    {
        if ($callback === null) {
            // null値を除外
            $callback = fn($value) => $value !== null;
        }

        // array_filterを使用してインスタンスコピーを回避
        $filtered = array_filter($this->items, $callback, ARRAY_FILTER_USE_BOTH);

        return new static($filtered);
    }

    /**
     * 指定されたキーと値でフィルタ
     * 
     * @param string $key
     * @param mixed $operator
     * @param mixed $value
     * @return static
     */
    public function where($key, $operator = null, $value = null)
    {
        // 引数が2つの場合は $operator が実際の値
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $filtered = array_filter($this->items, function ($item) use ($key, $operator, $value) {
            $retrieved = data_get($item, $key);

            return match ($operator) {
                '=' => $retrieved == $value,
                '==' => $retrieved == $value,
                '===' => $retrieved === $value,
                '!=' => $retrieved != $value,
                '!==' => $retrieved !== $value,
                '<' => $retrieved < $value,
                '>' => $retrieved > $value,
                '<=' => $retrieved <= $value,
                '>=' => $retrieved >= $value,
                default => $retrieved == $value,
            };
        });

        return new static($filtered);
    }

    /**
     * 指定されたキーの値が配列内に存在するアイテムでフィルタ
     * 
     * @param string $key
     * @param mixed $values
     * @param bool $strict
     * @return static
     */
    public function whereIn($key, $values, $strict = false)
    {
        $values = is_array($values) ? $values : [$values];

        $filtered = array_filter($this->items, function ($item) use ($key, $values, $strict) {
            $retrieved = data_get($item, $key);
            return in_array($retrieved, $values, $strict);
        });

        return new static($filtered);
    }

    /**
     * 指定されたキーの値が配列内に存在しないアイテムでフィルタ
     * 
     * @param string $key
     * @param mixed $values
     * @param bool $strict
     * @return static
     */
    public function whereNotIn($key, $values, $strict = false)
    {
        $values = is_array($values) ? $values : [$values];

        $filtered = array_filter($this->items, function ($item) use ($key, $values, $strict) {
            $retrieved = data_get($item, $key);
            return !in_array($retrieved, $values, $strict);
        });

        return new static($filtered);
    }

    /**
     * 条件に一致しないアイテムでフィルタ（filterの逆）
     * 
     * @param callable|mixed $callback
     * @return static
     */
    public function reject($callback = true)
    {
        // callableでない場合は親クラスの実装を使用
        if (!is_callable($callback)) {
            return parent::reject($callback);
        }

        $filtered = array_filter($this->items, function ($item, $key) use ($callback) {
            return !$callback($item, $key);
        }, ARRAY_FILTER_USE_BOTH);

        return new static($filtered);
    }

    /**
     * 最初のアイテムを取得（条件指定可能）
     * 
     * @param callable|null $callback
     * @param mixed $default
     * @return mixed
     */
    public function first(?callable $callback = null, $default = null)
    {
        if ($callback === null) {
            // 条件なしの場合は最初の要素を返す
            foreach ($this->items as $item) {
                return $item;
            }
            return value($default);
        }

        // 条件付きの場合は早期リターンでループを中断
        foreach ($this->items as $key => $item) {
            if ($callback($item, $key)) {
                return $item;
            }
        }

        return value($default);
    }

    /**
     * 指定されたキーと値に一致する最初のアイテムを取得
     * 
     * @param string $key
     * @param mixed $operator
     * @param mixed $value
     * @return mixed
     */
    public function firstWhere($key, $operator = null, $value = null)
    {
        // 引数が2つの場合は $operator が実際の値
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        // 早期リターンでループを中断
        foreach ($this->items as $item) {
            $retrieved = data_get($item, $key);

            $matches = match ($operator) {
                '=' => $retrieved == $value,
                '==' => $retrieved == $value,
                '===' => $retrieved === $value,
                '!=' => $retrieved != $value,
                '!==' => $retrieved !== $value,
                '<' => $retrieved < $value,
                '>' => $retrieved > $value,
                '<=' => $retrieved <= $value,
                '>=' => $retrieved >= $value,
                default => $retrieved == $value,
            };

            if ($matches) {
                return $item;
            }
        }

        return null;
    }

    /**
     * コレクションの値のみを含む新しいコレクションを返す
     * 
     * array_values()を通すのでキーは必ず0始まりのintになる。
     * 
     * @return static<int, TValue>
     */
    public function values()
    {
        return new static(array_values($this->items));
    }

    /**
     * 指定されたキーのみを含むコレクションを返す
     * 
     * @param mixed $keys
     * @return static
     */
    public function only($keys)
    {
        if ($keys === null) {
            return new static($this->items);
        }

        $keys = is_array($keys) ? $keys : func_get_args();

        // array_intersect_keyを使って高速化
        $filtered = array_intersect_key($this->items, array_flip($keys));

        return new static($filtered);
    }

    /**
     * 指定されたキーを除いたコレクションを返す
     * 
     * @param mixed $keys
     * @return static
     */
    public function except($keys)
    {
        if ($keys === null) {
            return new static($this->items);
        }

        $keys = is_array($keys) ? $keys : func_get_args();

        // array_diff_keyを使って高速化
        $filtered = array_diff_key($this->items, array_flip($keys));

        return new static($filtered);
    }

    /**
     * キーでソートした新しいコレクションを返す
     * 
     * @param int $options
     * @param bool $descending
     * @return static
     */
    public function sortKeys($options = SORT_REGULAR, $descending = false)
    {
        $items = $this->items;

        $descending ? krsort($items, $options) : ksort($items, $options);

        return new static($items);
    }

    /**
     * 値でソートした新しいコレクションを返す
     * 
     * @param int $options
     * @param bool $descending
     * @return static
     */
    public function sort($options = SORT_REGULAR, $descending = false)
    {
        $items = $this->items;

        $descending ? arsort($items, $options) : asort($items, $options);

        return new static($items);
    }

    /**
     * 指定されたキーの値でソートした新しいコレクションを返す
     * 
     * @param callable|string $callback
     * @param int $options
     * @param bool $descending
     * @return static
     */
    public function sortBy($callback, $options = SORT_REGULAR, $descending = false)
    {
        $items = $this->items;

        // キーでソートの場合は最適化
        if (is_string($callback)) {
            $results = [];
            foreach ($items as $key => $value) {
                $results[$key] = data_get($value, $callback);
            }

            $descending ? arsort($results, $options) : asort($results, $options);

            $sorted = [];
            foreach (array_keys($results) as $key) {
                $sorted[$key] = $items[$key];
            }

            return new static($sorted);
        }

        // callableの場合は親クラスの実装を使用
        return parent::sortBy($callback, $options, $descending);
    }
}
