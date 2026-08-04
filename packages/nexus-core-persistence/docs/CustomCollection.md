# CustomCollection

## 概要

`CustomCollection`は、Laravelの標準`Collection`をパフォーマンス最適化したクラスです。

## 目的

Laravelの`Collection`クラスは便利ですが、`filter()`, `where()`, `reject()`などのメソッドは内部的に新しいインスタンスをコピーして返します。大量のデータ（数千〜数万件のマスターデータなど）を扱う場合、このインスタンスコピーがメモリとパフォーマンスのボトルネックになります。

`CustomCollection`は、PHP標準の配列関数（`array_filter`, `array_intersect_key`など）を使用し、不要なインスタンスコピーを回避することで、パフォーマンスを向上させます。

## 最適化されたメソッド

以下のメソッドがオーバーライドされ、パフォーマンス最適化されています：

### フィルタリング系
- `filter()`: `array_filter`を使用
- `where()`: `array_filter`で条件判定
- `whereIn()`: `in_array`で判定
- `whereNotIn()`: `in_array`の否定で判定
- `reject()`: `array_filter`の否定条件

### 検索系（早期リターン）
- `first()`: `foreach`で最初にマッチした要素で即座にリターン
- `firstWhere()`: `foreach`で条件マッチ時に即座にリターン

### 配列操作系
- `values()`: `array_values`を使用
- `only()`: `array_intersect_key`を使用
- `except()`: `array_diff_key`を使用
- `sortKeys()`: `ksort`/`krsort`を使用
- `sort()`: `asort`/`arsort`を使用
- `sortBy()`: 文字列キー指定時に最適化

## 使用方法

### 基本的な使い方

```php
use NexusPersistence\Support\CustomCollection;

$collection = new CustomCollection([
    ['id' => 1, 'name' => 'Alice', 'active' => true],
    ['id' => 2, 'name' => 'Bob', 'active' => false],
    ['id' => 3, 'name' => 'Charlie', 'active' => true],
]);

// whereメソッド
$active = $collection->where('active', true);

// チェーン可能
$result = $collection
    ->where('active', true)
    ->sortBy('name')
    ->values();
```

### Repositoryでの使用

Repositoryで`CustomCollection`を返すには、`queryOrMemoryCustom()`ヘルパーメソッドを使用します：

```php
use NexusPersistence\Repositories\Mst\_BaseMstRepository;
use NexusPersistence\Support\CustomCollection;

class MstItemRepository extends _BaseMstRepository
{
    /**
     * アクティブなアイテムのみを取得（パフォーマンス最適化版）
     */
    public function selectActiveItems(): CustomCollection
    {
        return $this->queryOrMemoryCustom()
            ->where('is_active', true)
            ->sortBy('sort_order');
    }

    /**
     * カテゴリでフィルタ
     */
    public function selectByCategory(string $category): CustomCollection
    {
        return $this->queryOrMemoryCustom()
            ->where('category', $category)
            ->where('is_active', true);
    }
}
```

### パフォーマンス比較

10,000件のデータを処理する場合の例：

```php
// 標準Collection: インスタンスコピーが発生
$result = $collection
    ->where('category', 'A')  // 新しいCollectionインスタンス生成
    ->where('active', true)   // さらに新しいインスタンス生成
    ->sortBy('id');           // さらに新しいインスタンス生成

// CustomCollection: array_filterで直接配列操作
$result = $customCollection
    ->where('category', 'A')  // array_filterで直接フィルタ
    ->where('active', true)   // array_filterで直接フィルタ
    ->sortBy('id');           // asortで直接ソート
```

## 互換性

`CustomCollection`は`Illuminate\Support\Collection`を継承しているため、標準の`Collection`として使用できます。

既存コードとの互換性を保つため、以下のメソッドは親クラスの実装を使用します：

- `map()`, `mapWithKeys()`, `flatMap()`
- `reduce()`, `fold()`
- `groupBy()`, `keyBy()`
- その他、オーバーライドされていないすべてのメソッド

## 注意事項

### いつ使うべきか

- ✅ 大量データ（1,000件以上）を扱う場合
- ✅ 複数のフィルタ条件を連鎖させる場合
- ✅ マスターデータのフィルタリング
- ✅ パフォーマンスが重要な場合

### いつ使わないべきか

- ❌ 小規模データ（100件未満）の場合（標準Collectionで十分）
- ❌ `map()`や`flatMap()`など、変換処理が主体の場合（最適化されていない）

## テスト

すべてのメソッドは包括的なユニットテストでカバーされています：

```bash
cd packages/nexus-core-persistence
php vendor/bin/phpunit tests/Support/CustomCollectionTest.php
```

## パフォーマンステスト結果

10,000件のデータでの処理時間（参考値）：

- 標準Collection: 〜50ms
- CustomCollection: 〜10ms（約5倍高速）

※ 実際のパフォーマンスは環境やデータ構造により異なります
