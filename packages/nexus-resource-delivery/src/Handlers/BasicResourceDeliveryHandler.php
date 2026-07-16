<?php

namespace NexusResourceDelivery\Handlers;

use NexusResource\Enums\ResourceType;
use NexusResourceDelivery\DTOs\ResourceDeliveryContentDto;
use App\Domain\Wallet\Services\WalletService;

/**
 * BasicResourceDeliveryHandler
 * 
 * 基本リソース配送処理を担当するHandler
 * WalletServiceを使用して、Food, Wood, Stone, Iron, Stamina, Experience等を加算
 * 
 * 対応リソース:
 * - ResourceType::FOOD
 * - ResourceType::WOOD
 * - ResourceType::STONE
 * - ResourceType::IRON
 * - ResourceType::STAMINA
 * - ResourceType::EXPERIENCE
 */
class BasicResourceDeliveryHandler implements ResourceDeliveryHandlerInterface
{
    public function __construct(
        private readonly WalletService $walletService,
    ) {
    }

    /**
     * 基本リソース配送処理を実行
     * 
     * @param int $sysPlayerId プレイヤーID
     * @param ResourceDeliveryContentDto $content 配送コンテンツ
     * @return void
     * @throws \Exception 配送失敗時
     */
    public function handle(int $sysPlayerId, ResourceDeliveryContent $content): void
    {
        // 基本リソースは全て無償扱い
        $freeAmount = $content->getAmount();
        $paidAmount = 0;
        
        // WalletServiceのaddCurrencyメソッドを使用
        // 基本リソースは有効期限なし
        $this->walletService->addCurrency(
            $sysPlayerId,
            $content->getId(),
            $freeAmount,
            $paidAmount,
            null // 有効期限なし
        );
    }

    /**
     * このHandlerがサポートするリソースタイプかどうか
     * 
     * @param ResourceType|string $type リソースタイプ
     * @return bool
     */
    public function supports(ResourceType|string $type): bool
    {
        $typeValue = $type instanceof ResourceType ? $type->value : $type;
        
        return in_array($typeValue, [
            ResourceType::FOOD->value,
            ResourceType::WOOD->value,
            ResourceType::STONE->value,
            ResourceType::IRON->value,
            ResourceType::STAMINA->value,
            ResourceType::EXPERIENCE->value,
        ]);
    }
}
