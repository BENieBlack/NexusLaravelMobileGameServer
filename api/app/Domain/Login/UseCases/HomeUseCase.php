<?php

namespace App\Domain\Login\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\DoesNotReturnUpdatedResources;
use App\Domain\Login\Responses\HomeResponse;
use App\Exceptions\SystemDataException;
use App\Models\Sys\SysPlayer;
use App\Models\Trx\TrxDiamond;
use App\Models\Trx\TrxStamina;
use App\Persistence\ApiSession;
use App\Repositories\Sys\SysPlayerRepository;
use App\Repositories\Trx\TrxDiamondRepository;
use App\Repositories\Trx\TrxEquipmentRepository;
use App\Repositories\Trx\TrxItemRepository;
use App\Repositories\Trx\TrxStaminaRepository;
use App\Repositories\Trx\TrxUnitRepository;
use App\Repositories\Trx\TrxWalletRepository;
use NexusLogin\Services\LoginBonusOrchestrator;
use NexusStamina\Services\StaminaService;

/**
 * HomeUseCase
 *
 * ホーム画面データ取得のユースケース
 * - 日跨ぎ後の初回ログインであれば、カムバック・VIP・通常のログインボーナスを配布
 * - trx_unit、trx_item、trx_walletなどのユーザー情報を返す
 */
#[DoesNotReturnUpdatedResources('現在値を全て返す API なので、更新分だけを返す updated_resources は使わない')]
class HomeUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly SysPlayerRepository $sysPlayerRepository,
        private readonly LoginBonusOrchestrator $loginBonusOrchestrator,
        private readonly TrxUnitRepository $trxUnitRepository,
        private readonly TrxItemRepository $trxItemRepository,
        private readonly TrxWalletRepository $trxWalletRepository,
        private readonly TrxDiamondRepository $trxDiamondRepository,
        private readonly TrxEquipmentRepository $trxEquipmentRepository,
        private readonly TrxStaminaRepository $trxStaminaRepository,
        private readonly StaminaService $staminaService,
    ) {}

    /**
     * バリデーション
     *
     * @return SysPlayer 存在を確かめたプレイヤー（handle() で再取得しない）
     *
     * @throws SystemDataException プレイヤーが存在しない場合
     */
    public function validation(int $sysPlayerId): SysPlayer
    {
        // プレイヤー情報の存在確認
        $sysPlayer = $this->sysPlayerRepository->selectById($sysPlayerId);

        if (! $sysPlayer) {
            throw SystemDataException::generic('sys_player', $sysPlayerId);
        }

        return $sysPlayer;
    }

    /**
     * ログイン処理を実行
     *
     * @throws \Exception
     */
    public function handle(int $sysPlayerId): HomeResponse
    {
        // バリデーション実行（存在を確かめたプレイヤーが返る）
        $sysPlayer = $this->validation($sysPlayerId);

        return $this->executeWithTransaction(function () use ($sysPlayerId, $sysPlayer) {

            // 登録済みの全ログインボーナス（カムバック・VIP・通常）をチェック・配布
            // （履歴の書き込み先のシャードは ApiSession が解決する）
            //
            // 注意: 日跨ぎの判定に「更新前の」last_login_at を使う。
            // 下の updateLastLoginAt() より前に呼ぶ必要があり、
            // 最終ログインの更新をミドルウェア等に移すとボーナスが出なくなる。
            $loginBonusContents = $this->loginBonusOrchestrator->executeAllMerged(
                $sysPlayerId,
                $sysPlayer->getLastLoginAt(),
            );

            // 最終ログイン日時を更新
            $now = ApiSession::getNow();
            $this->sysPlayerRepository->updateLastLoginAt($sysPlayer, $now);

            // ユーザー情報を取得
            // queryOrMemory()はApiSessionからsysPlayerIdを取得するため、引数不要
            $trxUnits = $this->trxUnitRepository->queryOrMemory();
            $trxItems = $this->trxItemRepository->queryOrMemory();
            $trxWallets = $this->trxWalletRepository->queryOrMemory();
            // ダイヤは全行を取得したあとで、X-Platform の platform の 1 行だけを選ぶ。
            // DB 側で絞らないのは、同じリクエストのログインボーナスが積んだ未保存の行も見えるようにするため
            // （queryOrMemory はメモリ上のキューも含む）。行が無ければ null
            $currentPlatform = ApiSession::getPlatform();
            $trxDiamond = $this->trxDiamondRepository->queryOrMemory()
                ->first(fn (TrxDiamond $diamond) => $diamond->getPlatform() === $currentPlatform);
            $trxEquipments = $this->trxEquipmentRepository->queryOrMemory();

            return new HomeResponse(
                sysPlayer: $sysPlayer,
                trxUnits: $trxUnits->values()->all(),
                trxItems: $trxItems->values()->all(),
                trxWallets: $trxWallets->values()->all(),
                trxDiamond: $trxDiamond,
                trxEquipments: $trxEquipments->values()->all(),
                trxStaminas: $this->recoveredTrxStaminas($sysPlayerId),
                loginBonusContents: $loginBonusContents,
            );
        });
    }

    /**
     * スタミナを、自然回復を加算した現在値で返す（DBには保存しない）
     *
     * 保存するのは、アイテム・課金で回復したときと、消費したときだけ。ここでは、
     * キャッシュ上のモデルは書き換えず、回復後の値を入れた写しを返す（後の flush で保存されないように）。
     *
     * @return array<int, TrxStamina>
     */
    private function recoveredTrxStaminas(int $sysPlayerId): array
    {
        $recovered = [];
        foreach ($this->staminaService->findAllStaminas($sysPlayerId) as $stamina) {
            $recovered[$stamina->getType()] = $stamina;
        }

        $trxStaminas = [];
        foreach ($this->trxStaminaRepository->selectAllByPlayer() as $trxStamina) {
            $copy = $trxStamina->newInstance($trxStamina->getAttributes(), true);

            $stamina = $recovered[$trxStamina->getType()] ?? null;
            if ($stamina !== null) {
                $copy->setCurrentStamina($stamina->getCurrentStamina());
                $copy->setLastRecoveryAt($stamina->getLastRecoveryAt());
            }

            $trxStaminas[] = $copy;
        }

        return $trxStaminas;
    }
}
