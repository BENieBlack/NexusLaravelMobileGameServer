<?php

namespace App\Domain\LoginBonus\DataTransferObjects;

/**
 * LoginBonusRewardResult
 *
 * カレンダーの 1 日分の報酬 1 件（API の reward_list の要素。unique_id・expire_at は持たない）
 */
class LoginBonusRewardResult
{
    /**
     * @param  string  $type  ResourceType の値
     * @param  string  $id  content_mst_id
     * @param  int  $amount  配る量（content_quantity × amount）
     * @param  array<string, mixed>|null  $metadata  is_paid・content_option。無ければ null
     */
    public function __construct(
        public readonly string $type,
        public readonly string $id,
        public readonly int $amount,
        public readonly ?array $metadata,
    ) {}
}
