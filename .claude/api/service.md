# Service 実装ルール

[← APIドキュメントに戻る](../api.md) | [← ホームに戻る](../README.md)

このドキュメントでは、Serviceクラス（ドメインロジック層）の実装ルールを定義します。

詳細は[コーディング規約 - Serviceの実装ルール](../coding-standards.md#3-serviceの実装ルール)を参照してください。

---

## 基本原則

- **ドメインロジックの実装**に集中
- 再利用可能なビジネスロジックを提供
- Eloquentモデルを使用してデータアクセス
- 複雑なクエリはRepositoryに委譲
- HTTPリクエスト/レスポンスに依存しない

---

## 実装例

```php
class VersionCheckService
{
    public function checkVersion(?int $currentDeployId): VersionCheckResponse
    {
        $latestDeploy = SysDeploy::getLatestDownloadable();
        
        if ($latestDeploy === null) {
            return VersionCheckResponse::upToDate();
        }
        
        if ($currentDeployId === $latestDeploy->id) {
            return VersionCheckResponse::upToDate();
        }
        
        return new VersionCheckResponse(
            needsUpdate: true,
            latestDeployId: $latestDeploy->id,
            latestDeployKey: $latestDeploy->deploy_key,
            // ...
        );
    }
}
```

---

## Strategy Pattern（Handler）

複雑な分岐ロジックはStrategy Patternで実装します。

```php
class DeliveryService
{
    public function __construct(
        /** @var DeliveryHandlerInterface[] */
        private readonly array $handlers
    ) {}

    public function deliver(int $playerId, array $contents): array
    {
        $results = [];
        
        foreach ($contents as $content) {
            $handler = $this->findHandler($content);
            $results[] = $handler->deliver($playerId, $content);
        }
        
        return $results;
    }

    private function findHandler(DeliveryContent $content): DeliveryHandlerInterface
    {
        foreach ($this->handlers as $handler) {
            if ($handler->canHandle($content)) {
                return $handler;
            }
        }
        
        throw new UnsupportedResourceTypeException($content->resourceType);
    }
}
```

---

## チェックリスト

- [ ] ビジネスロジックを実装している
- [ ] EloquentモデルまたはRepositoryを使用
- [ ] HTTPリクエスト/レスポンスに依存していない
- [ ] 複雑な分岐はStrategy Patternで実装
- [ ] テスト可能な設計

---

[← APIドキュメントに戻る](../api.md) | [← ホームに戻る](../README.md)
