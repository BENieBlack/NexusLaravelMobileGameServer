<?php

namespace NexusResourceDelivery\Handlers;

use App\Domain\InAppPurchase\Services\InAppPurchaseDiamondBalanceService;
use NexusResource\Enums\ResourceType;
use NexusResourceDelivery\DataTransferObjects\ResourceDeliveryContent;

/**
 * DiamondDeliveryHandler
 *
 * ダイヤモンド配送処理を担当するHandler
 * InAppPurchaseDiamondBalanceServiceを使用して、有償/無償ダイヤモンドを加算
 *
 * 対応リソース:
 * - ResourceType::DIAMOND (無償ダイヤモンド)
 * - ResourceType::PAID_DIAMOND (有償ダイヤモンド)
 *
 * Note: platformはmetadataで指定する必要があります
 * metadata['platform']: プラットフォーム（Apple, Google）デフォルトは'Apple'
 */
class DiamondDeliveryHandler implements ResourceDeliveryHandlerInterface
{
    public function __construct(
        private readonly InAppPurchaseDiamondBalanceService $diamondBalanceService,
    ) {}

    /**
     * ダイヤモンド配送処理を実行
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  ResourceDeliveryContent  $resourceDeliveryContent  配送コンテンツ
     *
     * @throws \Exception 配送失敗時
     */
    public function handle(int $sysPlayerId, ResourceDeliveryContent $resourceDeliveryContent): void
    {
        // metadataからplatformを取得
        $metadata = $resourceDeliveryContent->getMetadata();
        $platform = $metadata['platform'] ?? 'Apple'; // デフォルトはApple

        // 有償/無償を判定
        $isPaid = $resourceDeliveryContent->getType() === ResourceType::PAID_DIAMOND;

        // ダイヤモンドを加算
        $this->diamondBalanceService->addDiamond(
            $sysPlayerId,
            $platform,
            $resourceDeliveryContent->getAmount(),
            $isPaid
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
            ResourceType::DIAMOND->value,
            ResourceType::PAID_DIAMOND->value,
        ]);
    }
}
