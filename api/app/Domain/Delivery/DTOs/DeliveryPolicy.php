<?php

namespace App\Domain\Delivery\DTOs;

use App\Domain\Delivery\Constants\DeliveryConst;
use App\Domain\Delivery\Enums\DeliveryMethod;

/**
 * DeliveryPolicy
 *
 * 配送時の動作を制御するポリシー
 *
 * デフォルト: 上限超過時はメールボックスへ送信
 * エラーモード: 上限超過時は例外を投げる
 */
class DeliveryPolicy
{
    /**
     * @param DeliveryMethod $itemMethod アイテム配送方法
     * @param DeliveryMethod $unitMethod ユニット配送方法
     * @param DeliveryMethod $diamondMethod ダイヤモンド配送方法
     * @param DeliveryMethod $walletMethod Wallet通貨配送方法
     * @param \Exception|null $resourceLimitReachedException リソース上限超過時に投げる例外
     */
    public function __construct(
        private DeliveryMethod $itemMethod = DeliveryMethod::NONE,
        private DeliveryMethod $unitMethod = DeliveryMethod::NONE,
        private DeliveryMethod $diamondMethod = DeliveryMethod::SEND_TO_MAILBOX,
        private DeliveryMethod $walletMethod = DeliveryMethod::NONE,
        private ?\Exception $resourceLimitReachedException = null,
    ) {
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
            itemMethod: DeliveryMethod::THROW_ERROR_WHEN_RESOURCE_LIMIT_REACHED,
            unitMethod: DeliveryMethod::NONE, // 重複変換があるので例外は投げない
            diamondMethod: DeliveryMethod::THROW_ERROR_WHEN_RESOURCE_LIMIT_REACHED,
            walletMethod: DeliveryMethod::THROW_ERROR_WHEN_RESOURCE_LIMIT_REACHED,
            resourceLimitReachedException: $resourceLimitReachedException,
        );
    }

    /**
     * リソースタイプごとの配送方法を取得
     *
     * @param string $contentType
     * @return DeliveryMethod
     */
    public function getMethodByContentType(string $contentType): DeliveryMethod
    {
        return match ($contentType) {
            DeliveryConst::CONTENT_TYPE_ITEM => $this->itemMethod,
            DeliveryConst::CONTENT_TYPE_UNIT => $this->unitMethod,
            DeliveryConst::CONTENT_TYPE_DIAMOND => $this->diamondMethod,
            DeliveryConst::CONTENT_TYPE_WALLET => $this->walletMethod,
            default => DeliveryMethod::NONE,
        };
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
     * @param array<string> $contentTypes チェック対象のリソースタイプ
     * @return array<string> 上限超過時に例外を投げるリソースタイプの配列
     */
    public function getContentTypesOfThrowErrorWhenResourceLimitReached(array $contentTypes): array
    {
        $result = [];

        $contentTypes = array_unique($contentTypes);

        foreach ($contentTypes as $contentType) {
            $method = $this->getMethodByContentType($contentType);
            if ($method === DeliveryMethod::THROW_ERROR_WHEN_RESOURCE_LIMIT_REACHED) {
                $result[] = $contentType;
            }
        }

        return $result;
    }
}
