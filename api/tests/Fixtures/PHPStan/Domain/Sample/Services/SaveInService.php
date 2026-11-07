<?php

namespace Tests\Fixtures\PHPStan\Domain\Sample\Services;

use Illuminate\Database\Eloquent\Model;

class SaveInService
{
    public function violates(Model $model): void
    {
        $model->save();
        $model->update([]);
        $model->delete();
        $model->forceDelete();
    }

    public function allowed(Model $model): void
    {
        $model->getKey();
    }
}
