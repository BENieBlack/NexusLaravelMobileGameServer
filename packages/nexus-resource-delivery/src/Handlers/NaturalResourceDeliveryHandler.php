<?php

namespace NexusResourceDelivery\Handlers;

use NexusResource\Enums\ResourceType;
use NexusResourceDelivery\DataTransferObjects\ResourceDeliveryContent;
use NexusWallet\Services\WalletService;

/**
 * NaturalResourceDeliveryHandler
 *
 * 自然資源の配送処理を担当するHandler
 * WalletServiceを使用して、Food, Wood, Stone, Ironを加算
 *
 * 対応リソース:
 * - ResourceType::FOOD
 * - ResourceType::WOOD
 * - ResourceType::STONE
 * - ResourceType::IRON
 *
 * スタミナと経験値は同じ「リソース系」だが管理方法が違うため、
 * StaminaDeliveryHandler / ExperienceDeliveryHandler が担当する。
 */
class NaturalResourceDeliveryHandler implements ResourceDeliveryHandlerInterface
{
    public function __construct(
        private readonly WalletService $walletService,
    ) {}

    /**
     * 自然資源の配送処理を実行
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  ResourceDeliveryContent  $resourceDeliveryContent  配送コンテンツ
     *
     * @throws \Exception 配送失敗時
     */
    public function handle(int $sysPlayerId, ResourceDeliveryContent $resourceDeliveryContent): void
    {
        // 自然資源は全て無償扱い
        $freeAmount = $resourceDeliveryContent->getAmount();
        $paidAmount = 0;

        // WalletServiceのaddCurrencyメソッドを使用
        // 自然資源は有効期限なし
        $this->walletService->addCurrency(
            $sysPlayerId,
            $resourceDeliveryContent->getId(),
            $freeAmount,
            $paidAmount,
            null // 有効期限なし
        );
    }

    /**
     * このHandlerがサポートするリソースタイプかどうか
     *
     * @param  ResourceType|string  $type  リソースタイプ
     */
    public function supports(ResourceType|string $type): bool
    {
        $resourceType = $type instanceof ResourceType ? $type : ResourceType::tryFrom($type);

        return $resourceType?->isNaturalResource() ?? false;
    }
}
