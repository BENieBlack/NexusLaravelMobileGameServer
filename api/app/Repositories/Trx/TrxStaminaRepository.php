<?php

namespace App\Repositories\Trx;

use App\Models\Trx\TrxStamina;
use App\Persistence\ApiSession;
use Illuminate\Support\Collection;
use Nexus\Core\Support\CustomCollection;

/**
 * TrxStaminaRepository
 *
 * スタミナデータの永続化のみを担当
 * ビジネスロジックはStaminaServiceに実装
 *
 * 常にEloquent Modelを返す。DTOが必要な箇所は
 * StaminaRepositoryAdapterを経由すること。
 *
 * PRIMARY KEY: (sys_player_id, type)
 *
 * @extends _BaseTrxRepository<TrxStamina>
 */
class TrxStaminaRepository extends _BaseTrxRepository
{
    protected string $modelClass = TrxStamina::class;

    /**
     * ユニークキー（trx_stamina の主キー）
     *
     * 既定の ['id'] のままだとtrx_staminaにはidが無いため、
     * キャッシュのキーが全行で同じになり1件しか保持できない
     *
     * @var list<string>
     */
    protected array $uniqueKeys = ['sys_player_id', 'type'];

    protected string $selectKey = 'sys_player_id';

    public function __construct(
        private readonly ApiSession $apiSession
    ) {}

    /**
     * プレイヤーの指定タイプのスタミナ情報を取得
     * queryOrMemory()経由でキャッシュからfilterして取得
     *
     * @param  string  $type  スタミナタイプ
     */
    public function selectByType(string $type): ?TrxStamina
    {
        // queryOrMemory()で全データをキャッシュにロード（ApiSessionから$sysPlayerIdを取得）
        $modelCollection = $this->queryOrMemory();

        // typeでフィルタして取得
        /** @var TrxStamina|null */
        return $modelCollection->where('type', $type)->first();
    }

    /**
     * プレイヤーの全てのスタミナ情報を取得
     *
     * @return CustomCollection<string, TrxStamina> キーはユニークキー（sys_player_id:type）
     */
    public function selectAllByPlayer(): Collection
    {
        // queryOrMemory()で全データをキャッシュにロード
        return $this->queryOrMemory();
    }

    /**
     * 指定プレイヤーの指定タイプのスタミナ情報を取得
     *
     * ApiSessionのプレイヤーIDを一時的に差し替えてから
     * selectByType()のキャッシュ機構を利用する。
     */
    public function selectByPlayerAndType(int $sysPlayerId, string $type): ?TrxStamina
    {
        // 未設定のまま getPlayerId() を呼ぶと例外になるので、あるときだけ退避する
        $originalPlayerId = $this->apiSession->hasPlayerId()
            ? $this->apiSession->getPlayerId()
            : null;
        $this->apiSession->setSysPlayerId($sysPlayerId);

        $stamina = $this->selectByType($type);

        // ApiSessionを元に戻す
        if ($originalPlayerId !== null) {
            $this->apiSession->setSysPlayerId($originalPlayerId);
        }

        return $stamina;
    }

    /**
     * スタミナレコードを新規登録キューに積む
     */
    public function insertStamina(
        int $sysPlayerId,
        string $type,
        int $currentStamina,
        float $recoveryRateMultiplier,
        mixed $lastRecoveryAt
    ): TrxStamina {
        $stamina = new TrxStamina([
            'sys_player_id' => $sysPlayerId,
            'type' => $type,
            'current_stamina' => $currentStamina,
            'recovery_rate_multiplier' => $recoveryRateMultiplier,
            'last_recovery_at' => $lastRecoveryAt,
        ]);
        $stamina->exists = false;
        $this->setModel($stamina);

        return $stamina;
    }
}
