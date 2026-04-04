<?php

namespace App\Domain\Delivery\DTOs;

use Illuminate\Support\Collection;

/**
 * DeliveryComplete
 *
 * 送信完了コンテンツの情報を保持するDTO
 */
readonly class DeliveryComplete
{
    /**
     * @param Collection<DeliveryContent> $contents 送信完了コンテンツのリスト
     */
    public function __construct(
        private Collection $contents,
    ) {
    }

    /**
     * 送信完了コンテンツのリストを取得
     *
     * @return Collection<DeliveryContent>
     */
    public function getContents(): Collection
    {
        return $this->contents;
    }
}
