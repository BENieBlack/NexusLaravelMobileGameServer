<?php

namespace App\Domain\Version\Responses;

use App\Domain\_BaseResponse;
use App\Models\Sys\SysDeploy;
use App\Models\Sys\SysMaintenance;

class CheckResponse extends _BaseResponse
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
            $data['maintenance'] = $this->sysMaintenance->toArray();
        }

        if ($this->needsUpdate && $this->sysDeploy !== null) {
            $data['sys_deploy_id'] = $this->sysDeploy->getKey();
            $data['latest_deploy_key'] = $this->sysDeploy->getDeployKey();

            // マスターデータ情報
            if ($this->sysDeploy->deployMaster !== null) {
                // キーはマスターのテーブル名（table_name 列）。getTableName() は DB テーブル名で全行同じ値になる
                $tableArray = [];
                foreach ($this->sysDeploy->deployMaster->tables as $table) {
                    $tableArray[$table->getMasterTableName()] = [
                        'hash' => $table->getHash(),
                        'url' => $table->getPublicUrl(),
                        'file_size' => $table->getFileSize(),
                    ];
                }
                $data['master'] = [
                    'sys_deploy_master_id' => $this->sysDeploy->getSysDeployMasterId(),
                    'hash' => $this->sysDeploy->deployMaster->getHash(),
                    'tables' => $tableArray,
                ];
            }

            // アセットデータ情報
            if ($this->sysDeploy->deployAsset !== null) {
                $data['asset'] = [
                    'sys_deploy_asset_id' => $this->sysDeploy->getSysDeployAssetId(),
                    'hash' => $this->sysDeploy->deployAsset->getHash(),
                ];
            }
        }

        return $data;
    }
}
