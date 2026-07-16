<?php

namespace NexusResourceDelivery\DTOs;

use Illuminate\Support\Collection;

/**
 * ResourceDeliveryComplete
 *
 * 送信完了コンテンツの情報を保持するDTO
 */
class ResourceDeliveryCompleteDto
{
    /**
     * @param Collection<ResourceDeliveryContent> $contents 送信完了コンテンツのリスト
     */
    public function __construct(
        
        private Collection $contents,
    ) {
    }

    /**
     * 送信完了コンテンツのリストを取得
     *
     * @return Collection<ResourceDeliveryContent>
     */
    public function getContents(): Collection
    {
        return $this->contents;
    }
}
