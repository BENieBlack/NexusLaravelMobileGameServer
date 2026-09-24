<?php

namespace App\Repositories\Trx;

use App\Models\Trx\TrxDiamond;
use Nexus\Core\Support\CustomCollection;

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
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  string  $platform  プラットフォーム（Apple, Google）
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

    /**
     * プレイヤーIDで全プラットフォームのダイヤモンドを取得
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @return CustomCollection<int, TrxDiamond>
     */
    public function selectByPlayerId(int $sysPlayerId): CustomCollection
    {
        // メモリ内キューから検索
        $queue = $this->queryOrMemory();
        $fromMemory = $queue->filter(function ($model) use ($sysPlayerId) {
            return $model->sys_player_id === $sysPlayerId;
        });

        // DBから検索
        $fromDb = TrxDiamond::query()
            ->where('sys_player_id', $sysPlayerId)
            ->where('is_delete', false)
            ->get();

        // メモリ側はユニークキー（string）、DB側は連番（int）でキーの体系が違う。
        // そのままmergeすると混ざるので、どちらも値だけに詰め直してから重複を排除する。
        return $fromMemory->values()
            ->merge($fromDb->values()->all())
            ->unique('id')
            ->values();
    }
}
