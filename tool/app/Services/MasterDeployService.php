<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class MasterDeployService
{
    /**
     * エクスポート結果を sys に登録する
     *
     * sys_deploy_master に新規レコードを作成し、
     * sys_deploy に紐づける（sys_deploy_asset は今回は未使用、NULLではなくダミーIDを使う）
     *
     * @param  array{
     *   hash: string,
     *   file_size: int,
     *   table_count: int,
     *   file_count: int,
     *   tables: array<string, array{hash: string, file_name: string, file_size: int, public_url: string}>,
     * }  $exportResult
     * @return array{
     *   sys_deploy_master_id: int,
     *   sys_deploy_id: int,
     *   deploy_key: int,
     *   hash: string,
     *   tables: array<string, array{hash: string, file_name: string, file_size: int, public_url: string}>,
     * }
     */
    public function register(array $exportResult): array
    {
        $now = now();
        $today = $now->format('Y-m-d');

        // 同じハッシュが既に登録済みか確認
        $existing = DB::connection('sys')
            ->table('sys_deploy_master')
            ->where('hash', $exportResult['hash'])
            ->first();

        if ($existing) {
            // 既存のデプロイを返す
            $deploy = DB::connection('sys')
                ->table('sys_deploy')
                ->where('sys_deploy_master_id', $existing->id)
                ->orderByDesc('id')
                ->first();
            $tableResultArray = DB::connection('sys')
                ->table('sys_deploy_master_table')
                ->where('sys_deploy_master_id', $existing->id)
                ->get()
                ->keyBy('table_name');

            foreach ($tableResultArray as $tableName => $table) {
                $publicUrl = "/masterdata/{$existing->hash}/{$table->file_name}";
                DB::connection('sys')
                    ->table('sys_deploy_master_table')
                    ->where('sys_deploy_master_id', $existing->id)
                    ->where('table_name', $tableName)
                    ->update(['public_url' => $publicUrl]);
                $tableResultArray[$tableName] = [
                    'hash' => $table->hash,
                    'file_name' => $table->file_name,
                    'file_size' => $table->file_size,
                    'public_url' => $publicUrl,
                ];
            }

            return [
                'sys_deploy_master_id' => $existing->id,
                'sys_deploy_id'        => $deploy?->id,
                'deploy_key'           => $existing->deploy_key,
                'hash'                 => $existing->hash,
                'tables'               => $tableResultArray,
                'is_new'               => false,
            ];
        }

        // deploy_key を生成 (YYYYMMDDN形式)
        $deployKey = $this->generateDeployKey($today);

        // deploy_count を取得
        $deployCount = DB::connection('sys')
            ->table('sys_deploy_master')
            ->whereDate('deploy_date', $today)
            ->count() + 1;

        // sys_deploy_master に登録
        $deployMasterId = DB::connection('sys')->table('sys_deploy_master')->insertGetId([
            'deploy_key'  => $deployKey,
            'hash'        => $exportResult['hash'],
            'deploy_date' => $today,
            'deploy_count'=> $deployCount,
            'status'      => 'completed',
            'deployed_by' => 'master_import',
            'deployed_at' => $now->format('Y-m-d H:i:s'),
            'description' => "マスターインポート: {$exportResult['table_count']}テーブル / {$exportResult['file_size']}bytes",
            'created_at'  => $now->format('Y-m-d H:i:s'),
            'updated_at'  => $now->format('Y-m-d H:i:s'),
        ]);

        foreach ($exportResult['tables'] as $tableName => $tableResult) {
            DB::connection('sys')->table('sys_deploy_master_table')->insert([
                'sys_deploy_master_id' => $deployMasterId,
                'table_name' => $tableName,
                'hash' => $tableResult['hash'],
                'file_size' => $tableResult['file_size'],
                'file_name' => $tableResult['file_name'],
                'public_url' => $tableResult['public_url'],
            ]);
        }

        // sys_deploy_asset にもレコードが必要（sys_deploy が NOT NULL で参照するため）
        // 今回はアセットなしなので最小限のレコードを作成
        $deployAssetId = DB::connection('sys')->table('sys_deploy_asset')->insertGetId([
            'deploy_key'   => $deployKey,
            'hash'         => $exportResult['hash'],
            'deploy_date'  => $today,
            'deploy_count' => $deployCount,
            'status'       => 'completed',
            'deployed_by'  => 'master_import',
            'deployed_at'  => $now->format('Y-m-d H:i:s'),
            'description'  => 'マスターデータのみ（アセットなし）',
            'created_at'   => $now->format('Y-m-d H:i:s'),
            'updated_at'   => $now->format('Y-m-d H:i:s'),
        ]);

        // 既存の is_active を全て false にする
        DB::connection('sys')->table('sys_deploy')->update(['is_active' => false]);

        // sys_deploy に登録（即アクティブ化）
        $deployId = DB::connection('sys')->table('sys_deploy')->insertGetId([
            'deploy_key'           => $deployKey,
            'start_at'             => $now->format('Y-m-d H:i:s'),
            'sys_deploy_master_id' => $deployMasterId,
            'sys_deploy_asset_id'  => $deployAssetId,
            'is_active'            => true,
            'created_at'           => $now->format('Y-m-d H:i:s'),
            'updated_at'           => $now->format('Y-m-d H:i:s'),
        ]);

        return [
            'sys_deploy_master_id' => $deployMasterId,
            'sys_deploy_id'        => $deployId,
            'deploy_key'           => $deployKey,
            'hash'                 => $exportResult['hash'],
            'tables'               => $exportResult['tables'],
            'is_new'               => true,
        ];
    }

    /**
     * deploy_key を生成する（YYYYMMDDN形式）
     * 例: 2026090401（2026年9月4日の1回目）
     */
    private function generateDeployKey(string $date): int
    {
        $dateInt = (int) str_replace('-', '', $date); // 20260904
        $count   = DB::connection('sys')
            ->table('sys_deploy_master')
            ->whereDate('deploy_date', $date)
            ->count() + 1;

        return $dateInt * 100 + $count; // 2026090401
    }
}
