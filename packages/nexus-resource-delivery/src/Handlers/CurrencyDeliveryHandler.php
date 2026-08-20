<?php

namespace NexusResourceDelivery\Handlers;

use NexusWallet\Services\WalletService;
use NexusResource\Enums\ResourceType;
use NexusResourceDelivery\DataTransferObjects\ResourceDeliveryContent;

/**
 * CurrencyDeliveryHandler
 *
 * 通貨配送処理を担当するHandler
 * WalletServiceを使用して、Gold, Coin等の通貨を加算
 *
 * 対応リソース:
 * - ResourceType::GOLD
 * - ResourceType::COIN
 */
class CurrencyDeliveryHandler implements ResourceDeliveryHandlerInterface
{
    public function __construct(
        private readonly WalletService $walletService,
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

        // WalletServiceのaddCurrencyメソッドを使用
        // expireAtはResourceから取得（NULLの場合は無期限）
        $this->walletService->addCurrency(
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
