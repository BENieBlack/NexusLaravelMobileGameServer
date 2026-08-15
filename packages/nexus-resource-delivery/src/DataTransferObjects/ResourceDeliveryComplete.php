<?php

namespace NexusResourceDelivery\DataTransferObjects;

use Nexus\Core\Support\CustomCollection;

/**
 * ResourceDeliveryComplete
 *
 * 送信完了コンテンツの情報を保持するDTO
 */
class ResourceDeliveryComplete
{
    /**
     * @param  CustomCollection<ResourceDeliveryContent>  $contents  送信完了コンテンツのリスト
     */
    public function __construct(

        private CustomCollection $contents,
    ) {}

    /**
     * 送信完了コンテンツのリストを取得
     *
     * @return CustomCollection<ResourceDeliveryContent>
     */
    public function getContents(): CustomCollection
    {
        return $this->contents;
    }
}
