# Repository 実装ルール

[← APIドキュメントに戻る](../api.md) | [← ホームに戻る](../README.md)

このドキュメントでは、Repositoryパターンの実装ルールを定義します。

詳細は[コーディング規約 - Repositoryの実装ルール](../coding-standards.md#7-repositoryの実装ルール)を参照してください。

---

## 基本原則

- **複雑なクエリをカプセル化**
- Eloquentモデルへのアクセスを抽象化
- テストしやすい設計
- ビジネスロジックは含めない（データアクセスのみ）

---

## 実装例

### 基本的なRepository

```php
namespace App\Repositories\Sys;

use App\Models\Sys\SysPlayer;
use Illuminate\Support\Collection;

class SysPlayerRepository
{
    /**
     * IDでプレイヤーを取得
     */
    public function findById(int $id): ?SysPlayer
    {
        return SysPlayer::find($id);
    }
    
    /**
     * my_idでプレイヤーを取得
     */
    public function findByMyId(string $myId): ?SysPlayer
    {
        return SysPlayer::where('my_id', $myId)->first();
    }
    
    /**
     * レベル範囲でプレイヤーを取得
     */
    public function findByLevelRange(int $minLevel, int $maxLevel): Collection
    {
        return SysPlayer::whereBetween('level', [$minLevel, $maxLevel])->get();
    }
    
    /**
     * プレイヤーを保存
     */
    public function save(SysPlayer $player): bool
    {
        return $player->save();
    }
}
```

### マスターデータRepository

```php
namespace App\Repositories\Mst;

use App\Models\Mst\MstItem;
use App\Models\Mst\_BaseMst;
use Illuminate\Support\Collection;

class MstItemRepository extends _BaseMstRepository
{
    protected function getModelClass(): string
    {
        return MstItem::class;
    }
    
    /**
     * アイテムタイプで絞り込み
     */
    public function findByType(string $type): Collection
    {
        return $this->getAllFromCache()
            ->where('type', $type)
            ->values();
    }
    
    /**
     * レアリティで絞り込み
     */
    public function findByRarity(int $rarity): Collection
    {
        return $this->getAllFromCache()
            ->where('rarity', $rarity)
            ->values();
    }
}
```

---

## メソッド命名規約

**重要: Repositoryのメソッド名は、データソース（sys/mst/trx/log）によって異なります。**

### Trx/Log Repository（プレイヤーデータ）

**プレイヤーIDが自動的にApiSessionから取得されるため、検索メソッドは`find*()`を使用します。**

| メソッド名 | 用途 | 戻り値 | 例 |
|-----------|------|--------|-----|
| `findById(int $id)` | ID検索（プレイヤーID込み） | `?Model` | `findById(123)` |
| `findByMstItemId(string $mstItemId)` | マスターID検索 | `?Model` | `findByMstItemId('item_001')` |
| `findAll()` | 全件取得（プレイヤーID込み） | `Collection` | `findAll()` |
| `findAllByType(string $type)` | タイプで絞り込み | `Collection` | `findAllByType('Attack')` |

**命名の理由:**
- `find*()`: プレイヤーIDが暗黙的に含まれるため、`select*()` ではなく `find*()` を使用
- `findAll*()`: 複数件取得時は `findAll` プレフィックスを使用

### Sys/Mst Repository（システム・マスターデータ）

**プレイヤーIDに依存しないデータのため、`select*()`を使用します。**

| メソッド名 | 用途 | 戻り値 | 例 |
|-----------|------|--------|-----|
| `selectById(int $id)` | ID検索 | `?Model` | `selectById(123)` |
| `selectByMyId(string $myId)` | my_id検索 | `?Model` | `selectByMyId('player_001')` |
| `selectAll()` | 全件取得 | `Collection` | `selectAll()` |
| `selectListByType(string $type)` | タイプで絞り込み | `Collection` | `selectListByType('Attack')` |

**命名の理由:**
- `select*()`: データベースから直接選択する操作を明示
- `selectList*()`: 複数件取得時は `selectList` プレフィックスを使用

### 共通ルール

| メソッド名 | 用途 | 戻り値 |
|-----------|------|--------|
| `save(Model $model)` | 保存 | `bool` |
| `delete(Model $model)` | 削除 | `bool` |
| `setModel(Model $model)` | モデルをキューに追加（Trx only） | `void` |

---

## チェックリスト

- [ ] 複雑なクエリをカプセル化
- [ ] メソッド名が意図を明確に表現
- [ ] ビジネスロジックを含まない
- [ ] テストしやすい設計
- [ ] マスターデータは`_BaseMstRepository`を継承

---

[← APIドキュメントに戻る](../api.md) | [← ホームに戻る](../README.md)
