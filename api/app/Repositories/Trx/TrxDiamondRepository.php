<?php

namespace App\Repositories\Trx;

use App\Models\Trx\TrxDiamond;
use App\Persistence\ApiSession;

/**
 * TrxDiamondRepository
 *
 * ダイヤモンド現在値管理Repository
 * 複合主キー: (sys_player_id, platform)
 * データアクセスのみを担当し、ビジネスロジックはServiceに委譲
 * 
 * @extends _BaseTrxRepository<TrxDiamond>
 */
class TrxDiamondRepository extends _BaseTrxRepository
{
    protected string $modelClass = TrxDiamond::class;

    /**
     * プレイヤーIDとプラットフォームでダイヤモンドを取得
     * 
     * @param int $sysPlayerId プレイヤーID
     * @param string $platform プラットフォーム（Apple, Google）
     * @return TrxDiamond|null
     */
    public function selectByPlatform(int $sysPlayerId, string $platform): ?TrxDiamond
    {
        
        // メモリ内キューから検索
        $queue = $this->queryOrMemory();
        $found = $queue->first(function ($model) use ($sysPlayerId, $platform) {
            return $model->sys_player_id === $sysPlayerId 
                && $model->platform === $platform;
        });

        if ($found) {
            return $found;
        }

        // DBから検索
        return TrxDiamond::query()
            ->where('sys_player_id', $sysPlayerId)
            ->where('platform', $platform)
            ->first();
    }
}
