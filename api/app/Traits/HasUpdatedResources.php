<?php

namespace App\Traits;

use Nexus\Core\UnitOfWork\UpdatedResources;

/**
 * HasUpdatedResources
 *
 * 「この処理で更新された trx」（updated_resources）を持つ Response のトレイト
 *
 * UseCase は、executeWithTransaction() が返した Response に、コミット後に取り出した更新を付けて返す。
 *
 *     return $this->executeWithTransaction(fn () => new XxxResponse(...))
 *         ->withUpdatedResources($this->pullUpdatedResources());
 *
 * PHP は、呼び出し先の式（executeWithTransaction）を先に評価してから引数を評価するので、
 * pullUpdatedResources() はコミット後に呼ばれる。
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
