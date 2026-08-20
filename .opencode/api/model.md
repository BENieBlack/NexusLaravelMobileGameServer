# Model 実装ルール

[← APIドキュメントに戻る](../api.md) | [← ホームに戻る](../README.md)

このドキュメントでは、Eloquentモデルの実装ルールを定義します。

詳細は以下を参照してください：
- [コーディング規約 - Modelの実装ルール](../coding-standards.md#6-modelの実装ルール)
- [APIドキュメント - Model設計](../api.md#model設計)

---

## 基本原則

- データベースごとにディレクトリを分離（`Models/Sys/`, `Models/Trx/` 等）
- `$connection`, `$table`を明示的に指定
- 複合PRIMARY KEYの場合は`$primaryKey = null`, `$incrementing = false`
- Log Modelは特別なルールを適用（`created_at`のみ、`updated_at`なし）

---

## ディレクトリ構成

```
app/Models/
├── Adm/        # 管理DB
├── Tol/        # ツールDB  
├── Sys/        # システムDB
├── Mst/        # マスターDB
├── Log/        # ログDB
├── Trx1/       # トランザクションDB1
└── Trx2/       # トランザクションDB2
```

---

## 実装例

### 基本的なModel

```php
namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;

class SysPlayer extends Model
{
    protected $connection = 'sys';
    protected $table = 'sys_player';
    protected $primaryKey = 'id';
    public $incrementing = true;
    
    protected $fillable = [
        'my_id',
        'name',
        'level',
        'exp',
    ];
    
    protected $casts = [
        'level' => 'integer',
        'exp' => 'integer',
        // 日時はキャストしない（stringのまま扱う）
    ];
}
```

### 日時の扱い

**日時はDB取得からレスポンスまで一貫して`string`（`Y-m-d H:i:s`）で扱う。**

- `$casts` に `datetime` / `immutable_datetime` を書かない
- `@property` の型注釈も `string`（null許容なら `?string`）
- `created_at` / `updated_at` はEloquentが `$casts` と無関係にCarbonへ変換するため、
  `_BaseModel::getDates()` を空にして無効化している（タイムスタンプの自動設定は有効のまま）
- 比較は `Y-m-d H:i:s` が固定長で辞書順＝時系列順のため、文字列のまま `<` `>` で行える。
  加減算やパースが必要なときだけ `ClockUtility`（`isPast()` / `diffInSeconds()` / `parse()` 等）を使う
- 複合主キーのモデルは `App\Traits\CompositePrimaryKeyTrait` を使う

### 複合PRIMARY KEY

```php
namespace App\Models\Trx;

use Illuminate\Database\Eloquent\Model;

class TrxItem extends Model
{
    protected $connection = 'trx1';
    protected $table = 'trx_item';
    
    // 複合PRIMARY KEY
    protected $primaryKey = null;
    public $incrementing = false;
    
    protected $fillable = [
        'sys_player_id',
        'mst_item_id',
        'quantity',
    ];
}
```

### Log Model

```php
namespace App\Models\Log;

use App\Models\Log\_BaseLog;

class LogEquipment extends _BaseLog
{
    protected $connection = 'log';
    protected $table = 'log_equipment';
    
    protected $fillable = [
        'unique_request_id',
        'sys_player_id',
        'trx_equipment_id',
        'mst_equipment_id',
        'system_at',  // ビジネスロジック用の日時
        // created_atはMySQLが自動設定
    ];
    
    protected $casts = [
        // 日時はキャストしない（system_at, created_at ともにstringのまま扱う）
    ];
}
```

---

## APIレスポンス用メソッド

### toResponseArray()

**すべてのモデルは`_BaseModel`で定義された`toResponseArray()`メソッドを使用してAPIレスポンスに変換**

```php
abstract class _BaseModel extends Model
{
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
        
        // sys_player_idはユーザーに渡さない内部IDなので除外
        unset($array['sys_player_id']);
        
        return $array;
    }
}
```

**使用例**:

```php
// Responseクラスで使用
class LevelUpResponse implements Responsable
{
    public function __construct(
        public readonly TrxEquipment $trxEquipment,
        public readonly TrxItem $trxItem,
    ) {}
    
    public function toResponse($request): JsonResponse
    {
        return response()->json([
            'trx_equipment' => $this->trxEquipment->toResponseArray(),
            'trx_item' => $this->trxItem->toResponseArray(),
        ]);
    }
}
```

**メリット**:
- モデルに変換ロジックを集約（DRY原則）
- フィールド追加時にResponse側の修正不要
- 日付フィールドが自動的にISO8601形式に変換される
- 全モデルで統一されたレスポンス形式
- `sys_player_id`などの内部IDは自動的に除外される

---

## チェックリスト

- [ ] `$connection`を明示的に指定
- [ ] `$table`を明示的に指定  
- [ ] 複合PRIMARY KEYの場合、`$primaryKey = null`, `$incrementing = false`
- [ ] `$fillable`で許可するカラムを定義
- [ ] `$casts`で型キャストを定義（**日時はキャストしない**。下記「日時の扱い」を参照）
- [ ] Log Modelは`_BaseLog`を継承
- [ ] APIレスポンスには`toResponseArray()`を使用

---

[← APIドキュメントに戻る](../api.md) | [← ホームに戻る](../README.md)
