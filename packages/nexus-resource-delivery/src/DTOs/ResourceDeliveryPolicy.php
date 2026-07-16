<?php

namespace NexusResourceDelivery\DTOs;

use NexusResource\Enums\ResourceType;
use NexusResourceDelivery\Enums\ResourceDeliveryMethod;

/**
 * ResourceDeliveryPolicy
 *
 * リソース配送時の動作を制御するポリシー
 *
 * デフォルト: 上限超過時はメールボックスへ送信
 * エラーモード: 上限超過時は例外を投げる
 */
class ResourceDeliveryPolicy
{
    /**
     * @param array<string, ResourceDeliveryMethod> $methodMap リソースタイプごとの配送方法
     * @param \Exception|null $resourceLimitReachedException リソース上限超過時に投げる例外
     */
    public function __construct(
        private array $methodMap = [],
        private ?\Exception $resourceLimitReachedException = null,
    ) {
        // デフォルト値を設定
        if (empty($this->methodMap)) {
            $this->methodMap = [
                ResourceType::DIAMOND->value => ResourceDeliveryMethod::SEND_TO_MAILBOX,
                ResourceType::PAID_DIAMOND->value => ResourceDeliveryMethod::SEND_TO_MAILBOX,
            ];
        }
    }

    /**
     * デフォルトポリシーを作成
     */
    public static function createDefaultPolicy(): self
    {
        return new self();
    }

    /**
     * リソース上限超過したら指定された例外を投げるポリシー
     *
     * @param \Exception $resourceLimitReachedException
     * @return self
     */
    public static function createThrowErrorWhenResourceLimitReachedPolicy(
        \Exception $resourceLimitReachedException,
    ): self {
        return new self(
            methodMap: [
                ResourceType::DIAMOND->value => ResourceDeliveryMethod::THROW_ERROR_WHEN_RESOURCE_LIMIT_REACHED,
                ResourceType::PAID_DIAMOND->value => ResourceDeliveryMethod::THROW_ERROR_WHEN_RESOURCE_LIMIT_REACHED,
                ResourceType::GOLD->value => ResourceDeliveryMethod::THROW_ERROR_WHEN_RESOURCE_LIMIT_REACHED,
                ResourceType::COIN->value => ResourceDeliveryMethod::THROW_ERROR_WHEN_RESOURCE_LIMIT_REACHED,
                ResourceType::FOOD->value => ResourceDeliveryMethod::THROW_ERROR_WHEN_RESOURCE_LIMIT_REACHED,
                ResourceType::WOOD->value => ResourceDeliveryMethod::THROW_ERROR_WHEN_RESOURCE_LIMIT_REACHED,
                ResourceType::STONE->value => ResourceDeliveryMethod::THROW_ERROR_WHEN_RESOURCE_LIMIT_REACHED,
                ResourceType::IRON->value => ResourceDeliveryMethod::THROW_ERROR_WHEN_RESOURCE_LIMIT_REACHED,
                ResourceType::ITEM->value => ResourceDeliveryMethod::THROW_ERROR_WHEN_RESOURCE_LIMIT_REACHED,
                ResourceType::CONSUMABLE->value => ResourceDeliveryMethod::THROW_ERROR_WHEN_RESOURCE_LIMIT_REACHED,
                ResourceType::MATERIAL->value => ResourceDeliveryMethod::THROW_ERROR_WHEN_RESOURCE_LIMIT_REACHED,
            ],
            resourceLimitReachedException: $resourceLimitReachedException,
        );
    }

    /**
     * リソースタイプごとの配送方法を取得
     *
     * @param ResourceType|string $resourceType
     * @return ResourceDeliveryMethod
     */
    public function getMethodByResourceType(ResourceType|string $resourceType): ResourceDeliveryMethod
    {
        $typeValue = $resourceType instanceof ResourceType ? $resourceType->value : $resourceType;
        return $this->methodMap[$typeValue] ?? ResourceDeliveryMethod::NONE;
    }

    /**
     * 配送方法を設定
     *
     * @param ResourceType|string $resourceType
     * @param ResourceDeliveryMethod $method
     * @return void
     */
    public function setMethod(ResourceType|string $resourceType, ResourceDeliveryMethod $method): void
    {
        $typeValue = $resourceType instanceof ResourceType ? $resourceType->value : $resourceType;
        $this->methodMap[$typeValue] = $method;
    }

    /**
     * 例外が設定されている場合は例外を投げる
     *
     * @return void
     * @throws \Exception
     */
    public function throwResourceLimitReachedExceptionIfSet(): void
    {
        if ($this->resourceLimitReachedException !== null) {
            throw $this->resourceLimitReachedException;
        }
    }

    /**
     * 指定されたリソースタイプのうち、上限超過時に例外を投げるものを返す
     *
     * @param array<ResourceType|string> $resourceTypes チェック対象のリソースタイプ
     * @return array<string> 上限超過時に例外を投げるリソースタイプの配列
     */
    public function getResourceTypesOfThrowErrorWhenResourceLimitReached(array $resourceTypes): array
    {
        $result = [];

        $uniqueTypes = [];
        foreach ($resourceTypes as $type) {
            $typeValue = $type instanceof ResourceType ? $type->value : $type;
            $uniqueTypes[$typeValue] = true;
        }

        foreach (array_keys($uniqueTypes) as $typeValue) {
            $method = $this->getMethodByResourceType($typeValue);
            if ($method === ResourceDeliveryMethod::THROW_ERROR_WHEN_RESOURCE_LIMIT_REACHED) {
                $result[] = $typeValue;
            }
        }

        return $result;
    }
}
