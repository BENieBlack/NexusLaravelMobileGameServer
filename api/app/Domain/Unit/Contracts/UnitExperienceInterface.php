<?php

namespace App\Domain\Unit\Contracts;

/**
 * UnitExperienceInterface
 *
 * Unitモジュールの公開API。ユニットに経験値を加算する。
 */
interface UnitExperienceInterface
{
    /**
     * ユニットに経験値を加算する
     *
     * @return array{
     *   is_leveled_up: bool,
     *   before_level: int,
     *   after_level: int,
     *   total_exp: int,
     *   exp_to_next: int|null,
     *   rarity: string,
     *   max_level: int
     * }
     *
     * @throws \Exception ユニットが存在しない場合
     */
    public function addExpWithDetails(int $trxUnitId, int $exp): array;
}
