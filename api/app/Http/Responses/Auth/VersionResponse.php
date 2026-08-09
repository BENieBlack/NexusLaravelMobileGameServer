<?php

namespace App\Http\Responses\Auth;

use App\Http\Responses\_BaseResponse;
use App\Models\Sys\SysDeploy;
use App\Models\Sys\SysMaintenance;

class VersionResponse extends _BaseResponse
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

    /**
     * 配列に変換
     */
    public function toArray(): array
    {
        $data = [
            'needs_update' => $this->needsUpdate,
        ];

        // メンテナンス情報は常に含める
        if ($this->sysMaintenance !== null) {
            $data['dto_maintenance'] = $this->sysMaintenance->toArray();
        }

        if ($this->needsUpdate && $this->sysDeploy !== null) {
            $data['latest_deploy_id'] = $this->sysDeploy->id;
            $data['latest_deploy_key'] = $this->sysDeploy->deploy_key;

            // マスターデータ情報
            if ($this->sysDeploy->deployMaster !== null) {
                $data['dto_master'] = [
                    'deploy_master_id' => $this->sysDeploy->sys_deploy_master_id,
                    'hash' => $this->sysDeploy->deployMaster->hash,
                ];
            }

            // アセットデータ情報
            if ($this->sysDeploy->deployAsset !== null) {
                $data['dto_asset'] = [
                    'deploy_asset_id' => $this->sysDeploy->sys_deploy_asset_id,
                    'hash' => $this->sysDeploy->deployAsset->hash,
                ];
            }
        }

        return $data;
    }

    /**
     * 更新不要のレスポンスを生成
     */
    public static function upToDate(?SysMaintenance $sysMaintenance = null): self
    {
        return new self(needsUpdate: false, sysMaintenance: $sysMaintenance);
    }

    /**
     * 更新必要のレスポンスを生成
     */
    public static function updateAvailable(
        SysDeploy $sysDeploy,
        ?SysMaintenance $sysMaintenance = null
    ): self {
        return new self(
            needsUpdate: true,
            sysDeploy: $sysDeploy,
            sysMaintenance: $sysMaintenance
        );
    }
}
