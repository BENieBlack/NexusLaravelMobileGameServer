# Request 実装ルール

[← APIドキュメントに戻る](../api.md) | [← ホームに戻る](../README.md)

このドキュメントでは、FormRequest（Requestクラス）の実装ルールを定義します。

---

## 目次

- [基本原則](#基本原則)
- [実装ルール](#実装ルール)
- [バリデーションルール](#バリデーションルール)
- [実装例](#実装例)
- [チェックリスト](#チェックリスト)

---

## 基本原則

### FormRequestに責務を集中

FormRequestは、入力データのバリデーションとデータ取得のみを担当します。

```php
// ✅ Good: FormRequestでバリデーション
class VersionCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // 認証はMiddlewareで行う
    }

    public function rules(): array
    {
        return [
            'deploy_version' => ['required', 'integer', 'min:1'],
        ];
    }
    
    public function getDeployVersion(): int
    {
        return $this->integer('deploy_version');
    }
}

// ❌ Bad: Controller内でバリデーション
class AuthController extends Controller
{
    public function version(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'deploy_version' => ['required', 'integer', 'min:1'],
        ]); // FormRequestで行うべき
    }
}
```

---

## 実装ルール

### 1. バリデーションルールの定義

**`rules()`メソッドで定義**

```php
public function rules(): array
{
    return [
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'unique:users'],
        'password' => ['required', 'string', 'min:8'],
    ];
}
```

### 2. 型安全なゲッター

**入力値を取得するゲッターメソッドを提供**

```php
public function getName(): string
{
    return $this->string('name')->toString();
}

public function getEmail(): string
{
    return $this->string('email')->toString();
}

public function getAge(): int
{
    return $this->integer('age');
}

public function getIsActive(): bool
{
    return $this->boolean('is_active');
}
```

**理由**:
- 型安全性の向上
- コード補完が効く
- 変更時の影響範囲が明確

### 3. 認証・認可

**認証はMiddlewareで、認可は`authorize()`で**

```php
// 通常は認証不要（Middlewareで制御）
public function authorize(): bool
{
    return true;
}

// 特定のリソースに対する認可が必要な場合のみ
public function authorize(): bool
{
    $player = Player::find($this->route('id'));
    return $this->user()->can('update', $player);
}
```

### 4. カスタムバリデーションメッセージ

**`messages()`メソッドでメッセージをカスタマイズ**

```php
public function messages(): array
{
    return [
        'name.required' => '名前は必須です',
        'email.email' => '有効なメールアドレスを入力してください',
        'password.min' => 'パスワードは8文字以上で入力してください',
    ];
}
```

### 5. カスタムバリデーション属性名

**`attributes()`メソッドで属性名を日本語化**

```php
public function attributes(): array
{
    return [
        'name' => '名前',
        'email' => 'メールアドレス',
        'password' => 'パスワード',
    ];
}
```

---

## バリデーションルール

### よく使用するルール

| ルール | 説明 | 例 |
|--------|------|---|
| `required` | 必須 | `['required']` |
| `string` | 文字列 | `['string']` |
| `integer` | 整数 | `['integer']` |
| `boolean` | 真偽値 | `['boolean']` |
| `email` | メールアドレス | `['email']` |
| `min:{value}` | 最小値/最小長 | `['min:8']` |
| `max:{value}` | 最大値/最大長 | `['max:255']` |
| `in:{values}` | 列挙値 | `['in:apple,google']` |
| `exists:{table},{column}` | DB存在チェック | `['exists:users,id']` |
| `unique:{table},{column}` | DB重複チェック | `['unique:users,email']` |
| `array` | 配列 | `['array']` |
| `nullable` | NULL許可 | `['nullable']` |

### カスタムルールの使用

```php
use App\Rules\ValidPlatform;

public function rules(): array
{
    return [
        'platform' => ['required', new ValidPlatform()],
    ];
}
```

---

## 実装例

### 基本的なFormRequest

```php
namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VersionCheckRequest extends FormRequest
{
    /**
     * 認証チェック（通常はtrue）
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * バリデーションルール
     */
    public function rules(): array
    {
        return [
            'deploy_version' => ['required', 'integer', 'min:1'],
        ];
    }
    
    /**
     * 型安全なゲッター
     */
    public function getDeployVersion(): int
    {
        return $this->integer('deploy_version');
    }
}
```

### 複雑なバリデーション

```php
namespace App\Http\Requests\Player;

use Illuminate\Foundation\Http\FormRequest;

class CreatePlayerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'platform' => ['required', 'string', 'in:Apple,Google'],
            'device_id' => ['required', 'string', 'unique:sys_player,device_id'],
            'selected_character_id' => ['required', 'integer', 'exists:mst_character,id'],
        ];
    }
    
    public function messages(): array
    {
        return [
            'name.required' => 'プレイヤー名は必須です',
            'name.max' => 'プレイヤー名は50文字以内で入力してください',
            'platform.in' => '無効なプラットフォームです',
            'device_id.unique' => 'このデバイスは既に登録されています',
            'selected_character_id.exists' => '選択されたキャラクターは存在しません',
        ];
    }
    
    public function getName(): string
    {
        return $this->string('name')->toString();
    }
    
    public function getPlatform(): string
    {
        return $this->string('platform')->toString();
    }
    
    public function getDeviceId(): string
    {
        return $this->string('device_id')->toString();
    }
    
    public function getSelectedCharacterId(): int
    {
        return $this->integer('selected_character_id');
    }
}
```

### 配列データのバリデーション

```php
namespace App\Http\Requests\Item;

use Illuminate\Foundation\Http\FormRequest;

class BulkCreateItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.mst_item_id' => ['required', 'integer', 'exists:mst_item,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:9999'],
        ];
    }
    
    /**
     * アイテムリストを取得
     * 
     * @return array<int, array{mst_item_id: int, quantity: int}>
     */
    public function getItems(): array
    {
        return $this->input('items');
    }
}
```

### オプショナルなフィールド

```php
namespace App\Http\Requests\Player;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlayerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:50'],
            'avatar_url' => ['nullable', 'url', 'max:255'],
        ];
    }
    
    public function getName(): ?string
    {
        return $this->has('name') 
            ? $this->string('name')->toString() 
            : null;
    }
    
    public function getAvatarUrl(): ?string
    {
        return $this->has('avatar_url') 
            ? $this->string('avatar_url')->toString() 
            : null;
    }
}
```

---

## チェックリスト

FormRequest実装時に以下を確認してください：

### バリデーション

- [ ] `rules()`メソッドでバリデーションルールを定義している
- [ ] すべての入力項目にバリデーションルールがある
- [ ] 適切な型（string, integer, boolean等）を指定している
- [ ] 必要に応じて`min`, `max`を設定している

### ゲッターメソッド

- [ ] 各入力項目に型安全なゲッターを定義している
- [ ] ゲッターの戻り値の型を明示している
- [ ] オプショナルな項目は`?型`で定義している

### 認証・認可

- [ ] `authorize()`メソッドを実装している
- [ ] 通常は`return true;`（認証はMiddlewareで）
- [ ] リソースベースの認可が必要な場合のみ`authorize()`で判定

### メッセージ

- [ ] ユーザー向けのエラーメッセージをカスタマイズしている
- [ ] 日本語メッセージを提供している（日本向けゲームの場合）

### 命名

- [ ] クラス名は`{アクション}{リソース}Request`
- [ ] ゲッターメソッド名は`get{Property}()`

---

## 関連ドキュメント

- [Controller実装ルール](./controller.md) - Controllerでの使用方法
- [バリデーション公式ドキュメント](https://laravel.com/docs/validation) - Laravelのバリデーション
- [FormRequest公式ドキュメント](https://laravel.com/docs/validation#form-request-validation) - FormRequestの詳細

---

[← APIドキュメントに戻る](../api.md) | [← ホームに戻る](../README.md)
