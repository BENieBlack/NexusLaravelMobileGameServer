<?php

namespace NexusResourceDelivery\DTOs;

use NexusResource\DTOs\ResourceDto;
use NexusResource\Enums\ResourceType;
use NexusResourceDelivery\Enums\ResourceDeliveryStatus;
use NexusResourceDelivery\Enums\ResourceDeliveryResultReason;
use Ramsey\Uuid\Uuid;

/**
 * ResourceDeliveryContent
 *
 * 配布リソースのデータ構造
 * ResourceをラップしてDelivery処理に必要な情報を付加
 *
 * - 配送ステータス（未送信、送信済み、配布済み）
 * - 変換履歴（元のデータと変換後のデータを両方保持）
 * - 配送失敗理由（上限超過、メールボックス送信など）
 * - ログ用のbefore/after情報
 */
class ResourceDeliveryContentDto
{
    /** @var string DeliveryManagerでの管理のために使用する一意のID */
    private string $uniqueId;

    /** @var ResourceDeliveryStatus 配送ステータス */
    private ResourceDeliveryStatus $status;

    /** @var ResourceDeliveryResultReason 変換理由 */
    private ResourceDeliveryResultReason $conversionReason;

    /** @var ResourceDeliveryResultReason 配送失敗理由 */
    private ResourceDeliveryResultReason $failureReason;

    /** @var Resource|null 元のリソース（変換前） */
    private ?ResourceDto $originalResource = null;

    /** @var int ログ用：付与前のリソース量 */
    private int $beforeAmount = 0;

    /** @var int ログ用：付与後のリソース量 */
    private int $afterAmount = 0;

    /**
     * @param ResourceDto $resource リソース
     */
    public function __construct(
        private ResourceDto $resource,
    ) {
        $this->uniqueId = $this->generateUniqueId();
        $this->status = ResourceDeliveryStatus::PENDING;
        $this->conversionReason = ResourceDeliveryResultReason::NONE;
        $this->failureReason = ResourceDeliveryResultReason::NONE;
    }

    /**
     * 一意のIDを生成
     */
    private function generateUniqueId(): string
    {
        return (string) Uuid::uuid4();
    }

    /**
     * 一意のIDを取得
     */
    public function getUniqueId(): string
    {
        return $this->uniqueId;
    }

    /**
     * リソースを取得
     */
    public function getResource(): ResourceDto
    {
        return $this->resource;
    }

    /**
     * リソースタイプを取得
     */
    public function getType(): ResourceType
    {
        return $this->resource->getType();
    }

    /**
     * タイプの文字列を取得
     */
    public function getTypeValue(): string
    {
        return $this->resource->getTypeValue();
    }

    /**
     * マスターIDを取得
     */
    public function getId(): string
    {
        return $this->resource->getId();
    }

    /**
     * 数量を取得
     */
    public function getAmount(): int
    {
        return $this->resource->getAmount();
    }

    /**
     * 有効期限を取得
     */
    public function getExpireAt(): ?string
    {
        return $this->resource->getExpireAt();
    }

    /**
     * メタデータを取得
     */
    public function getMetadata(): ?array
    {
        return $this->resource->getMetadata();
    }

    /**
     * 配送ステータスを取得
     */
    public function getStatus(): ResourceDeliveryStatus
    {
        return $this->status;
    }

    /**
     * 送信完了としてマーク（配送処理完了時に呼び出し）
     * 即時配布の場合はRECEIVED、メールボックス送信の場合はDELIVEREDにする
     */
    public function markAsSendComplete(): void
    {
        // メールボックス送信などで即時配布できなかった場合はDELIVERED
        if ($this->hasFailed()) {
            $this->status = ResourceDeliveryStatus::DELIVERED;
        } else {
            // 即時配布の場合はRECEIVED
            $this->status = ResourceDeliveryStatus::RECEIVED;
        }
    }

    /**
     * メールボックス送信済みとしてマーク
     */
    public function markAsDelivered(): void
    {
        $this->status = ResourceDeliveryStatus::DELIVERED;
    }

    /**
     * 受取済みとしてマーク
     */
    public function markAsReceived(): void
    {
        $this->status = ResourceDeliveryStatus::RECEIVED;
    }

    /**
     * 送信完了かどうか（配送処理が完了しているか）
     */
    public function isSendComplete(): bool
    {
        return $this->status === ResourceDeliveryStatus::DELIVERED 
            || $this->status === ResourceDeliveryStatus::RECEIVED;
    }

    /**
     * 配送に失敗したかどうか（メールボックス送信などで即時配布されなかった）
     */
    public function hasFailed(): bool
    {
        return $this->failureReason !== ResourceDeliveryResultReason::NONE;
    }

    /**
     * 変換されたかどうか
     */
    public function isConverted(): bool
    {
        return $this->conversionReason !== ResourceDeliveryResultReason::NONE;
    }

    /**
     * 有効なコンテンツかどうか（数量が1以上）
     */
    public function isValid(): bool
    {
        return $this->resource->isValid();
    }

    /**
     * 変換理由を取得
     */
    public function getConversionReason(): ResourceDeliveryResultReason
    {
        return $this->conversionReason;
    }

    /**
     * 配送失敗理由を取得
     */
    public function getFailureReason(): ResourceDeliveryResultReason
    {
        return $this->failureReason;
    }

    /**
     * 配送失敗理由を設定
     */
    public function setFailureReason(ResourceDeliveryResultReason $reason): void
    {
        $this->failureReason = $reason;
    }

    /**
     * コンテンツを変換（元のリソースを保存してから新しいリソースに置き換える）
     *
     * @param ResourceDto $newResource 新しいリソース
     * @param ResourceDeliveryResultReason $reason 変換理由
     */
    public function convertTo(ResourceDto $newResource, ResourceDeliveryResultReason $reason): void
    {
        // 元のリソースを保存
        if ($this->originalResource === null) {
            $this->originalResource = $this->resource;
        }

        // 新しいリソースに置き換え
        $this->resource = $newResource;
        $this->conversionReason = $reason;
    }

    /**
     * 元のリソースを取得（変換前のリソース）
     */
    public function getOriginalResource(): ?Resource
    {
        return $this->originalResource;
    }

    /**
     * ログ用：付与前のリソース量を設定
     */
    public function setBeforeAmount(int $amount): void
    {
        $this->beforeAmount = $amount;
    }

    /**
     * ログ用：付与後のリソース量を設定
     */
    public function setAfterAmount(int $amount): void
    {
        $this->afterAmount = $amount;
    }

    /**
     * ログ用：付与前のリソース量を取得
     */
    public function getBeforeAmount(): int
    {
        return $this->beforeAmount;
    }

    /**
     * ログ用：付与後のリソース量を取得
     */
    public function getAfterAmount(): int
    {
        return $this->afterAmount;
    }

    /**
     * 配列からResourceDeliveryContentを生成
     *
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $resource = Resource::fromArray($data);
        return new self($resource);
    }

    /**
     * Resourceから生成
     *
     * @param ResourceDto $resource
     * @return self
     */
    public static function fromResource(ResourceDto $resource): self
    {
        return new self($resource);
    }

    /**
     * 配列に変換
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'unique_id' => $this->uniqueId,
            'resource' => $this->resource->toArray(),
            'status' => $this->status->value,
            'conversion_reason' => $this->conversionReason->value,
            'failure_reason' => $this->failureReason->value,
            'original_resource' => $this->originalResource?->toArray(),
            'before_amount' => $this->beforeAmount,
            'after_amount' => $this->afterAmount,
        ];
    }
}
