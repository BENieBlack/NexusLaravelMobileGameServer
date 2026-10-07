<?php

namespace App\Domain\Version\DataTransferObjects;

use App\Models\Sys\SysDeploy;
use App\Models\Sys\SysMaintenance;

/**
 * VersionResult
 *
 * バージョンチェックの結果
 */
class VersionResult
{
    /**
     * @param  bool  $needsUpdate  マスターデータまたはアセットの更新が必要か
     * @param  SysDeploy|null  $sysDeploy  最新のデプロイ情報（リレーション込み）
     * @param  SysMaintenance|null  $sysMaintenance  メンテナンス情報
     */
    public function __construct(
        public readonly bool $needsUpdate,
        public readonly ?SysDeploy $sysDeploy = null,
        public readonly ?SysMaintenance $sysMaintenance = null,
    ) {}
}
