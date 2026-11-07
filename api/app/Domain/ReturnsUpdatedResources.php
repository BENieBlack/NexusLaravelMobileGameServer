<?php

namespace App\Domain;

use Nexus\Core\UnitOfWork\UpdatedResources;

/**
 * ReturnsUpdatedResources
 *
 * 「この処理で更新された trx」（updated_resources）をレスポンスに載せる Response の印
 *
 * HasUpdatedResources トレイトで実装する。executeWithTransaction() は、この印のある Response に、
 * コミット後の更新を付けて返す。
 */
interface ReturnsUpdatedResources
{
    /**
     * 更新された trx を付けた、新しい Response を返す
     */
    public function withUpdatedResources(UpdatedResources $updatedResources): static;

    public function getUpdatedResources(): UpdatedResources;
}
