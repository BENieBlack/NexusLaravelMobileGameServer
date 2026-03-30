<?php

namespace App\Repositories\Sys;

use App\Models\Sys\SysPlayerDevice;
use App\Repositories\QueryManager;
use Illuminate\Support\Collection;

/**
 * SysPlayerDeviceRepository
 * 
 * プレイヤーデバイス情報のRepository実装
 */
class SysPlayerDeviceRepository extends _BaseSysRepository
{
    protected string $modelClass = SysPlayerDevice::class;

    /**
     * デバイスUUID（device_id）からデバイス情報を検索
     * メモリキャッシュから検索、なければDBから取得
     *
     * @param string $deviceId
     * @return SysPlayerDevice|null
     */
    public function selectByDeviceId(string $deviceId): ?SysPlayerDevice
    {
        // メモリキャッシュから検索
        $sysPlayerDevice = $this->getModels()->firstWhere('uuid', $deviceId);
        
        if ($sysPlayerDevice !== null) {
            /** @var SysPlayerDevice */
            return $sysPlayerDevice;
        }
        
        // DBから取得してメモリキャッシュに保存
        $sysPlayerDevice = $this->modelClass::where('uuid', $deviceId)->first();
        
        if ($sysPlayerDevice !== null) {
            $this->setModel($sysPlayerDevice);
        }
        
        return $sysPlayerDevice;
    }

    /**
     * プレイヤーIDからデバイス一覧を取得
     * メモリキャッシュから検索、なければDBから取得
     *
     * @param int $sysPlayerId sys_player.id（プレイヤーID）
     * @return Collection<int, SysPlayerDevice>
     */
    public function selectListByPlayerId(int $sysPlayerId): Collection
    {
        // メモリキャッシュから検索
        $sysPlayerDeviceCollection = $this->getModels()->where('sys_player_id', $sysPlayerId);
        
        if ($sysPlayerDeviceCollection->isNotEmpty()) {
            return $sysPlayerDeviceCollection->values();
        }
        
        // DBから取得してメモリキャッシュに保存
        $sysPlayerDeviceCollection = $this->modelClass::where('sys_player_id', $sysPlayerId)->get();
        
        foreach ($sysPlayerDeviceCollection as $sysPlayerDevice) {
            $this->setModel($sysPlayerDevice);
        }
        
        return $sysPlayerDeviceCollection;
    }

    /**
     * デバイスを作成（遅延コミット）
     * 
     * setModelでキューに追加するのみ。
     * トランザクション終了時やexecSysQuery()呼び出し時に一括コミットされる。
     * 
     * @param int $sysPlayerId sys_player.id（プレイヤーID）
     * @param string $deviceId デバイスUUID
     * @param array<string, mixed>|null $deviceInfo デバイス情報（JSON）
     * @return SysPlayerDevice 作成されたデバイス（IDは未設定）
     */
    public function createDevice(
        int $sysPlayerId,
        string $deviceId,
        ?array $deviceInfo = null
    ): SysPlayerDevice {
        $sysPlayerDevice = new SysPlayerDevice([
            'sys_player_id' => $sysPlayerId,
            'uuid' => $deviceId,
            'device_info' => $deviceInfo,
            'last_login_at' => now(),
        ]);
        
        $this->setModel($sysPlayerDevice);
        
        return $sysPlayerDevice;
    }

    /**
     * デバイスを作成して即座にコミット（即コミット専用）
     * 
     * SignUpなど、即座にIDが必要な場合に使用。
     * Repository内でexecSysQuery()を実行してIDを取得する。
     * 
     * @param int $sysPlayerId sys_player.id（プレイヤーID）
     * @param string $deviceId デバイスUUID
     * @param array<string, mixed>|null $deviceInfo デバイス情報（JSON）
     * @return SysPlayerDevice 作成されたデバイス（IDが設定済み）
     */
    public function createDeviceAndCommit(
        int $sysPlayerId,
        string $deviceId,
        ?array $deviceInfo = null
    ): SysPlayerDevice {
        $sysPlayerDevice = new SysPlayerDevice([
            'sys_player_id' => $sysPlayerId,
            'uuid' => $deviceId,
            'device_info' => $deviceInfo,
            'last_login_at' => now(),
        ]);
        
        $this->setModel($sysPlayerDevice);
        
        // Repository内でexecSysQuery()を実行してIDを取得
        app()->make(QueryManager::class)->execSysQuery();
        
        return $sysPlayerDevice;
    }
}
