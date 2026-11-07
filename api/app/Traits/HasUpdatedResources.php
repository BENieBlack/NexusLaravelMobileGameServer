<?php

namespace App\Traits;

use Nexus\Core\UnitOfWork\UpdatedResources;

/**
 * HasUpdatedResources
 *
 * 「この処理で更新された trx」（updated_resources）を持つ Response のトレイト
 *
 * UseCase は何もしなくてよい。executeWithTransaction() がコミットした後に、
 * このトレイトを使う Response へ、更新した trx を自動で付ける。
 *
 *     return $this->executeWithTransaction(fn () => new XxxResponse(...));
 */
trait HasUpdatedResources
{
    private ?UpdatedResources $updatedResources = null;

    /**
     * 更新された trx を付けた、新しい Response を返す（元の Response は変えない）
     */
    public function withUpdatedResources(UpdatedResources $updatedResources): static
    {
        $clone = clone $this;
        $clone->updatedResources = $updatedResources;

        return $clone;
    }

    public function getUpdatedResources(): UpdatedResources
    {
        return $this->updatedResources ?? new UpdatedResources;
    }
}
