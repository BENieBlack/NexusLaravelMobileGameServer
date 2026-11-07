<?php

namespace Tests\Fixtures\PHPStan\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Models/ 配下は、getAttribute() とプロパティ参照を許可する（getter の実装そのもの）
 *
 * @property int $free_amount
 */
class InsideModel extends Model
{
    public function getFreeAmount(): int
    {
        $other = new self;
        $other->getAttribute('free_amount');

        return $this->getAttribute('free_amount') + $other->free_amount;
    }
}
