<?php

namespace App\Domain\Delivery\Handlers;

use App\Domain\Delivery\Constants\DeliveryConst;
use App\Domain\Delivery\DTOs\DeliveryContent;
use App\Services\WalletService;

/**
 * WalletDeliveryHandler
 * 
 * 汎用通貨（Wallet）配送処理を担当するHandler
 * WalletServiceを使用して、Gold, EventCoin, RaidMedal等の通貨を加算
 */
class WalletDeliveryHandler implements _BaseDeliveryHandlerInterface
{
    public function __construct(
        private readonly WalletService $walletService,
    ) {
    }

    /**
     * 汎用通貨配送処理を実行
     * 
     * @param int $sysPlayerId プレイヤーID（後方互換性のため保持、ApiSessionから自動取得）
     * @param DeliveryContent $content 配送コンテンツ
     * @return void
     * @throws \Exception 配送失敗時
     */
    public function handle(int $sysPlayerId, DeliveryContent $content): void
    {
        // metadata['is_paid']が true の場合は有償、false または未設定の場合は無償
        $isPaid = $content->getMetadata()['is_paid'] ?? false;
        
        $freeAmount = $isPaid ? 0 : $content->getAmount();
        $paidAmount = $isPaid ? $content->getAmount() : 0;
        
        // WalletServiceのaddCurrencyメソッドを使用
        // expireAtはDeliveryContentから取得（NULLの場合は無期限）
        $this->walletService->addCurrency(
            $sysPlayerId,
            $content->getId(),
            $freeAmount,
            $paidAmount,
            $content->getExpireAt()
        );
    }

    /**
     * このHandlerがサポートするリソースタイプかどうか
     * 
     * @param string $type リソースタイプ
     * @return bool
     */
    public function supports(string $type): bool
    {
        return $type === DeliveryConst::CONTENT_TYPE_WALLET;
    }
}
