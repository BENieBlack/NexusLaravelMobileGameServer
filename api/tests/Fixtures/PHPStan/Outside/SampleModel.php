<?php

namespace Tests\Fixtures\PHPStan\Outside;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $free_amount
 */
class SampleModel extends Model
{
    public bool $nativeFlag = false;

    public function relation(): void {}
}
