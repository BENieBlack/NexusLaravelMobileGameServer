<?php

namespace App\Domain\Mailbox\UseCases;

use App\Domain\Delivery\DTOs\DeliveryContent;
use App\Domain\Delivery\Services\DeliveryService;
use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Http\Responses\Mailbox\ReceiveResponse;
use App\Repositories\Trx\TrxMailboxRepository;
use App\Traits\UseCaseTrait;

/**
 * ReceiveUseCase
 *
 * 添付配布物受取処理
 */
class ReceiveUseCase
{
    use UseCaseTrait;

    public function __construct(
        private TrxMailboxRepository $trxMailboxRepository,
        private DeliveryService $deliveryService,
    ) {
    }

    /**
     * 添付配布物を受け取る
     *
     * @param int $sysPlayerId
     * @param int $trxMailboxId
     * @return ReceiveResponse
     * @throws GameException
     */
    public function handle(int $sysPlayerId, int $trxMailboxId): ReceiveResponse
    {
        return $this->executeWithTransaction(function () use ($sysPlayerId, $trxMailboxId) {
            // メールボックス取得
            $trxMailbox = $this->trxMailboxRepository->selectById($trxMailboxId);

            if ($trxMailbox === null || $trxMailbox->getSysPlayerId() !== $sysPlayerId) {
                throw new GameException(GameErrorCode::MAILBOX_NOT_FOUND, 'Mailbox not found');
            }

            // 既に受取済みの場合
            if ($trxMailbox->getIsReceived()) {
                throw new GameException(GameErrorCode::MAILBOX_ALREADY_RECEIVED, 'Mailbox already received');
            }

            // マスターデータから添付物を取得
            $mstMailbox = $trxMailbox->mstMailbox;
            $contentCollection = $mstMailbox?->contentCollection ?? collect();

            // DeliveryContent配列に変換
            $deliveryContentArray = $contentCollection->map(function ($content) {
                return new DeliveryContent(
                    type: strtolower($content->getContentType()), // Diamond -> diamond
                    id: $content->getContentId(),
                    amount: $content->getAmount(),
                );
            })->toArray();

            // 配送処理
            if (count($deliveryContentArray) > 0) {
                $deliveryResult = $this->deliveryService->delivers($sysPlayerId, $deliveryContentArray);

                if (!$deliveryResult->isAllSuccess()) {
                    throw new GameException(GameErrorCode::INTERNAL_ERROR, 'Failed to deliver mailbox content');
                }
            }

            // 受取済みにする
            $this->trxMailboxRepository->markAsReceived($trxMailbox);

            return new ReceiveResponse($trxMailbox->getId(), true, $deliveryContentArray);
        });
    }
}
