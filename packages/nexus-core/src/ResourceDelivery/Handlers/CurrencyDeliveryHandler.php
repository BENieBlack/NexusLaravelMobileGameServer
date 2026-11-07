<?php

namespace Nexus\Core\ResourceDelivery\Handlers;

use Nexus\Core\Resource\Enums\ResourceType;
use Nexus\Core\ResourceDelivery\Contracts\CurrencyGranterInterface;
use Nexus\Core\ResourceDelivery\DataTransferObjects\ResourceDeliveryContent;

/**
 * CurrencyDeliveryHandler
 *
 * 通貨配送処理を担当するHandler
 * CurrencyGranterInterface を使って、Gold, Coin等の通貨を加算
 *
 * 対応リソース:
 * - ResourceType::GOLD
 * - ResourceType::COIN
 */
class CurrencyDeliveryHandler implements ResourceDeliveryHandlerInterface
{
    public function __construct(
        private readonly CurrencyGranterInterface $currencyGranter,
    ) {}

    /**
     * 通貨配送処理を実行
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  ResourceDeliveryContent  $resourceDeliveryContent  配送コンテンツ
     *
     * @throws \Exception 配送失敗時
     */
    public function handle(int $sysPlayerId, ResourceDeliveryContent $resourceDeliveryContent): void
    {
        // metadata['is_paid']が true の場合は有償、false または未設定の場合は無償
        $metadata = $resourceDeliveryContent->getMetadata();
        $isPaid = $metadata['is_paid'] ?? false;

        $freeAmount = $isPaid ? 0 : $resourceDeliveryContent->getAmount();
        $paidAmount = $isPaid ? $resourceDeliveryContent->getAmount() : 0;

        // CurrencyGranterInterface（実装は nexus-wallet）で付与する
        // expireAtはResourceから取得（NULLの場合は無期限）
        $this->currencyGranter->grantCurrency(
            $sysPlayerId,
            $resourceDeliveryContent->getId(),
            $freeAmount,
            $paidAmount,
            $resourceDeliveryContent->getExpireAt()
        );
    }

    /**
     * このHandlerがサポートするリソースタイプかどうか
     *
     * @param  ResourceType|string  $type  リソースタイプ
     */
    public function supports(ResourceType|string $type): bool
    {
        $typeValue = $type instanceof ResourceType ? $type->value : $type;

        return in_array($typeValue, [
            ResourceType::GOLD->value,
            ResourceType::COIN->value,
        ]);
    }
}
