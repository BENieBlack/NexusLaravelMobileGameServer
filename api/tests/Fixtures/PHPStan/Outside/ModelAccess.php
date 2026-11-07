<?php

namespace Tests\Fixtures\PHPStan\Outside;

/**
 * モデルの外のコード（NoGetAttributeOutsideModelsRule / NoModelColumnPropertyAccessRule の検査対象）
 */
class ModelAccess
{
    public function violations(SampleModel $model): void
    {
        $model->getAttribute('free_amount');
        $model->setAttribute('free_amount', 1);
        $value = $model->free_amount;
        $model->free_amount = 2;
    }

    public function allowed(SampleModel $model, \stdClass $row): void
    {
        $model->nativeFlag;
        $model->relation;
        $row->free_amount;
        $model->getKey();
    }
}
