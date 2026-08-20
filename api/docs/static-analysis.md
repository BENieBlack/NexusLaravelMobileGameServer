# 静的解析とアーキテクチャテスト

このプロジェクトでは、コード品質とアーキテクチャの整合性を保つために、PHPStanとアーキテクチャテストを導入しています。

## 目次

1. [PHPStan（静的解析）](#phpstan静的解析)
2. [アーキテクチャテスト](#アーキテクチャテスト)
3. [CI/CDでの実行](#cicdでの実行)
4. [カスタムルールの追加](#カスタムルールの追加)

---

## PHPStan（静的解析）

PHPStanは、コードを実行せずに型エラーやバグを検出するツールです。

### 実行方法

```bash
# Docker環境で実行
docker exec api-php composer phpstan

# または直接実行
docker exec api-php ./vendor/bin/phpstan analyse --memory-limit=2G
```

### カスタムルール

#### 1. Service層でのEloquent直接操作禁止

**ルール**: Service層で直接`save()`, `update()`, `delete()`, `forceDelete()`を呼び出すことを禁止

**理由**:
- QueryManagerのキューイング機能をバイパスしてしまう
- `updated_at`の自動設定が機能しない
- トランザクション管理が正しく動作しない
- テスト容易性が低下する

**正しい実装**:

```php
// ❌ Bad: Service層で直接save()を呼び出す
class UnitLevelService
{
    public function addExp(int $trxUnitId, int $exp): array
    {
        $trxUnit = $this->trxUnitRepository->findById($trxUnitId);
        $trxUnit->level = 10;
        $trxUnit->save();  // NG
        
        return ['level' => $trxUnit->level];
    }
}

// ✅ Good: Repository経由でDB操作
class UnitLevelService
{
    public function addExp(int $trxUnitId, int $exp): array
    {
        $trxUnit = $this->trxUnitRepository->findById($trxUnitId);
        $trxUnit->level = 10;
        $this->trxUnitRepository->setModel($trxUnit);  // OK
        
        return ['level' => $trxUnit->level];
    }
}
```

**実装場所**:
- `api/phpstan/Rules/NoDirectEloquentSaveInServiceRule.php`
- `api/phpstan.neon`（設定ファイル）

---

## アーキテクチャテスト

PHPUnitベースのアーキテクチャテストで、コードベースの構造的な制約を検証します。

### 実行方法

```bash
# Docker環境で実行
docker exec api-php ./vendor/bin/phpunit tests/Architecture/ServiceLayerTest.php

# または Composer経由
docker exec api-php composer test:arch
```

### テスト項目

#### 1. Service層でのEloquent直接操作禁止

Service層で`->save()`, `->update()`, `->delete()`, `->forceDelete()`を直接呼び出していないことを検証。

#### 2. 命名規約の検証

- **Service層**: クラス名が`Service`で終わること
- **UseCase層**: クラス名が`UseCase`で終わること
- **Repository層**: クラス名が`Repository`で終わること

#### 3. レイヤー間の依存関係（今後追加予定）

Clean Architectureの原則に従い、依存方向が正しいことを検証。

---

## CI/CDでの実行

### GitHub Actions（推奨設定）

```yaml
name: Static Analysis

on: [push, pull_request]

jobs:
  phpstan:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
      - name: Install dependencies
        run: composer install
      - name: Run PHPStan
        run: composer phpstan

  architecture-test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
      - name: Install dependencies
        run: composer install
      - name: Run Architecture Tests
        run: composer test:arch
```

### Pre-commit Hook（ローカル開発）

`.git/hooks/pre-commit`に以下を追加：

```bash
#!/bin/bash

echo "Running PHPStan..."
docker exec api-php composer phpstan
if [ $? -ne 0 ]; then
    echo "❌ PHPStan failed. Commit aborted."
    exit 1
fi

echo "Running Architecture Tests..."
docker exec api-php composer test:arch
if [ $? -ne 0 ]; then
    echo "❌ Architecture tests failed. Commit aborted."
    exit 1
fi

echo "✅ All checks passed!"
```

実行権限を付与：
```bash
chmod +x .git/hooks/pre-commit
```

---

## カスタムルールの追加

### PHPStanカスタムルールの追加手順

1. **ルールクラスを作成**

`api/phpstan/Rules/YourCustomRule.php`:

```php
<?php

namespace App\PHPStan\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

class YourCustomRule implements Rule
{
    public function getNodeType(): string
    {
        return Node\Expr\MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        // ルールのロジックを実装
        return [];
    }
}
```

2. **phpstan.neonに登録**

```yaml
rules:
    - App\PHPStan\Rules\NoDirectEloquentSaveInServiceRule
    - App\PHPStan\Rules\YourCustomRule  # 追加
```

3. **テスト実行**

```bash
docker exec api-php composer phpstan
```

### アーキテクチャテストの追加手順

1. **tests/Architecture/ServiceLayerTest.php**に新しいテストメソッドを追加

```php
public function test_your_architecture_rule(): void
{
    // テストロジックを実装
    $this->assertTrue(true);
}
```

2. **テスト実行**

```bash
docker exec api-php composer test:arch
```

---

## トラブルシューティング

### PHPStanのメモリ不足エラー

```bash
# メモリ制限を増やす
docker exec api-php ./vendor/bin/phpstan analyse --memory-limit=4G
```

### カスタムルールが認識されない

```bash
# オートローダーを再生成
docker exec api-php composer dump-autoload
```

### アーキテクチャテストが失敗する

```bash
# 詳細なエラーメッセージを確認
docker exec api-php ./vendor/bin/phpunit tests/Architecture/ServiceLayerTest.php --verbose
```

---

## 参考資料

- [PHPStan公式ドキュメント](https://phpstan.org/)
- [PHPUnit公式ドキュメント](https://phpunit.de/)
- [Clean Architectureガイド](../../.claude/architecture.md)
- [命名規約ガイド](../../.claude/naming-conventions.md)
