<?php

namespace App\Repositories\Sys;

use App\Models\Sys\SysPlayer;
use App\Repositories\QueryManager;
use Illuminate\Support\Str;

/**
 * SysPlayerRepository
 * 
 * プレイヤー情報のRepository実装
 */
class SysPlayerRepository extends _BaseSysRepository
{
    protected string $modelClass = SysPlayer::class;

    /**
     * IDでプレイヤーを検索
     * メモリキャッシュから取得、なければDBから取得
     *
     * @param int $id プレイヤーID
     * @return SysPlayer|null
     */
    public function findById(int $id): ?SysPlayer
    {
        // メモリキャッシュから取得
        $sysPlayer = $this->getModel($id);
        
        if ($sysPlayer !== null) {
            /** @var SysPlayer */
            return $sysPlayer;
        }
        
        // DBから取得してメモリキャッシュに保存
        $sysPlayer = $this->modelClass::find($id);
        
        if ($sysPlayer !== null) {
            $this->setModel($sysPlayer);
        }
        
        return $sysPlayer;
    }

    /**
     * my_idからプレイヤーを検索
     * メモリキャッシュから検索、なければDBから取得
     *
     * @param string $myId
     * @return SysPlayer|null
     */
    public function selectByMyId(string $myId): ?SysPlayer
    {
        // メモリキャッシュから検索
        $sysPlayer = $this->getModels()->firstWhere('my_id', $myId);
        
        if ($sysPlayer !== null) {
            /** @var SysPlayer */
            return $sysPlayer;
        }
        
        // DBから取得してメモリキャッシュに保存
        $sysPlayer = $this->modelClass::where('my_id', $myId)->first();
        
        if ($sysPlayer !== null) {
            $this->setModel($sysPlayer);
        }
        
        return $sysPlayer;
    }

    /**
     * UUIDからプレイヤーを検索
     * メモリキャッシュから検索、なければDBから取得
     *
     * @param string $uuid
     * @return SysPlayer|null
     */
    public function selectByUuid(string $uuid): ?SysPlayer
    {
        // メモリキャッシュから検索
        $sysPlayer = $this->getModels()->firstWhere('uuid', $uuid);
        
        if ($sysPlayer !== null) {
            /** @var SysPlayer */
            return $sysPlayer;
        }
        
        // DBから取得してメモリキャッシュに保存
        $sysPlayer = $this->modelClass::where('uuid', $uuid)->first();
        
        if ($sysPlayer !== null) {
            $this->setModel($sysPlayer);
        }
        
        return $sysPlayer;
    }

    /**
     * my_idが既に存在するかチェック
     *
     * @param string $myId
     * @return bool
     */
    public function existsByMyId(string $myId): bool
    {
        // メモリキャッシュから検索
        if ($this->getModels()->where('my_id', $myId)->isNotEmpty()) {
            return true;
        }
        
        // DBで確認
        return $this->modelClass::where('my_id', $myId)->exists();
    }

    /**
     * プレイヤーを作成してIDを取得
     * 
     * @return SysPlayer 作成されたプレイヤー（IDが設定済み）
     */
    public function createPlayer(): SysPlayer
    {
        $sysPlayer = new SysPlayer([
            'my_id' => Str::random(8),
            'uuid' => Str::uuid()->toString(),
            'name' => Str::random(8),
        ]);
        
        $this->setModel($sysPlayer);
        
        // Repository内でexecSysQuery()を実行してIDを取得
        app()->make(QueryManager::class)->execSysQuery();
        
        return $sysPlayer;
    }
}
