# Utility 実装ルール

[← APIドキュメントに戻る](../api.md) | [← ホームに戻る](../README.md)

このドキュメントでは、Utilityクラスの実装ルールを定義します。

詳細は[コーディング規約 - Utilityクラスの実装ルール](../coding-standards.md#8-utilityクラスの実装ルール)を参照してください。

---

## 基本原則

- **汎用的なヘルパー機能**を提供
- すべてのメソッドはstaticで実装
- インスタンス化不可（privateコンストラクタ）
- プロジェクト全体で再利用可能

---

## 実装例

### ClockUtility（日時管理）

```php
namespace App\Utilities;

use Carbon\CarbonImmutable;

class ClockUtility
{
    private function __construct() {}
    
    /**
     * 現在時刻を取得（デバッグ時刻設定に対応）
     */
    public static function now(): CarbonImmutable
    {
        // 本番: 実際の現在時刻
        // デバッグ: 設定された時刻
        return CarbonImmutable::now();
    }
    
    /**
     * 今日の開始時刻を取得
     */
    public static function startOfDay(): CarbonImmutable
    {
        return self::now()->startOfDay();
    }
    
    /**
     * 今日の終了時刻を取得
     */
    public static function endOfDay(): CarbonImmutable
    {
        return self::now()->endOfDay();
    }
}
```

### UniqueIdUtility（ID生成）

```php
namespace App\Utilities;

class UniqueIdUtility
{
    private function __construct() {}
    
    /**
     * ユニークなIDを生成
     */
    public static function generate(int $length = 8): string
    {
        $charset = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz123456789';
        $charsetLength = strlen($charset);
        
        $id = '';
        for ($i = 0; $i < $length; $i++) {
            $id .= $charset[random_int(0, $charsetLength - 1)];
        }
        
        return $id;
    }
}
```

---

## 命名規約

- クラス名: `{機能}Utility`
- メソッド: すべてstatic
- 短く、明確な名前

---

## チェックリスト

- [ ] すべてのメソッドはstatic
- [ ] privateコンストラクタでインスタンス化を防止
- [ ] 汎用的な機能のみ実装
- [ ] ビジネスロジックを含まない
- [ ] プロジェクト全体で再利用可能

---

[← APIドキュメントに戻る](../api.md) | [← ホームに戻る](../README.md)
