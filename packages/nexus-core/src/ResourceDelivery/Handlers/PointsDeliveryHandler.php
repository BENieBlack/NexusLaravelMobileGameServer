<?php

namespace Nexus\Core\ResourceDelivery\Handlers;

use Nexus\Core\Resource\Enums\ResourceType;
use Nexus\Core\ResourceDelivery\Contracts\CurrencyGranterInterface;
use Nexus\Core\ResourceDelivery\DataTransferObjects\ResourceDeliveryContent;

/**
 * PointsDeliveryHandler
 *
 * 各種ポイント配送処理を担当するHandler
 * CurrencyGranterInterface を使って、AlliancePoints, PvPPoints等のポイントを加算
 *
 * 対応リソース:
 * - ResourceType::ALLIANCE_POINTS
 * - ResourceType::PVP_POINTS
 * - ResourceType::EVENT_POINTS
 * - ResourceType::ACHIEVEMENT_POINTS
 * - ResourceType::VIP_POINTS
 */
class PointsDeliveryHandler implements ResourceDeliveryHandlerInterface
{
    public function __construct(
        private readonly CurrencyGranterInterface $currencyGranter,
    ) {}

    /**
     * ポイント配送処理を実行
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  ResourceDeliveryContent  $resourceDeliveryContent  配送コンテンツ
     *
     * @throws \Exception 配送失敗時
     */
    public function handle(int $sysPlayerId, ResourceDeliveryContent $resourceDeliveryContent): void
    {
        // ポイント系は全て無償扱い
        $freeAmount = $resourceDeliveryContent->getAmount();
        $paidAmount = 0;

        // CurrencyGranterInterface（実装は nexus-wallet）で付与する
        // ポイント系は基本的に有効期限なし
        $this->currencyGranter->grantCurrency(
            $sysPlayerId,
            $resourceDeliveryContent->getId(),
            $freeAmount,
            $paidAmount,
            $resourceDeliveryContent->getExpireAt() // イベントポイント等で有効期限がある場合に対応
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
            ResourceType::ALLIANCE_POINTS->value,
            ResourceType::PVP_POINTS->value,
            ResourceType::EVENT_POINTS->value,
            ResourceType::ACHIEVEMENT_POINTS->value,
            ResourceType::VIP_POINTS->value,
        ]);
    }
}
