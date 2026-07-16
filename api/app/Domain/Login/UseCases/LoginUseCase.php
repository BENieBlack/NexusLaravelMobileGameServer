<?php

namespace App\Domain\Login\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Login\Services\LoginBonusService;
use NexusResource\DTOs\Resource;
use App\Exceptions\SystemDataException;
use App\Http\Responses\Auth\LoginResponse;
use App\Repositories\Sys\SysPlayerRepository;
use App\Repositories\Trx\TrxItemRepository;
use App\Repositories\Trx\TrxUnitRepository;
use App\Repositories\Trx\TrxWalletRepository;
use App\Persistence\ApiSession;

/**
 * LoginUseCase
 * 
 * ログイン処理のユースケース
 * - UTC0時を境界として今日初回ログインであればログインボーナスを配布
 * - trx_unit、trx_item、trx_walletなどのユーザー情報を返す
 */
class LoginUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly SysPlayerRepository $sysPlayerRepository,
        private readonly LoginBonusService $loginBonusService,
        private readonly TrxUnitRepository $trxUnitRepository,
        private readonly TrxItemRepository $trxItemRepository,
        private readonly TrxWalletRepository $trxWalletRepository,
    ) {
    }

    /**
     * バリデーション
     * 
     * @param int $sysPlayerId
     * @return void
     * @throws SystemDataException プレイヤーが存在しない場合
     */
    public function validation(int $sysPlayerId): void
    {
        // プレイヤー情報の存在確認
        $sysPlayer = $this->sysPlayerRepository->selectById($sysPlayerId);

        if (!$sysPlayer) {
            throw SystemDataException::generic('sys_player', $sysPlayerId);
        }
    }

    /**
     * ログイン処理を実行
     * 
     * @param int $sysPlayerId
     * @return LoginResponse
     * @throws \Exception
     */
    public function handle(int $sysPlayerId): LoginResponse
    {
        // バリデーション実行
        $this->validation($sysPlayerId);

        return $this->executeWithTransaction(function () use ($sysPlayerId) {
            // プレイヤー情報を取得（バリデーション済み）
            $sysPlayer = $this->sysPlayerRepository->selectById($sysPlayerId);

            // ログインボーナスをチェック・配布
            $loginBonusContents = $this->loginBonusService->checkAndGrantLoginBonus(
                $sysPlayerId,
                $sysPlayer->getLastLoginAt()
            );

            // 最終ログイン日時を更新
            $now = ApiSession::getNow();
            $this->sysPlayerRepository->updateLastLoginAt($sysPlayer, $now);

            // ユーザー情報を取得
            // queryOrMemory()はApiSessionからsysPlayerIdを取得するため、引数不要
            $trxUnits = $this->trxUnitRepository->queryOrMemory();
            $trxItems = $this->trxItemRepository->queryOrMemory();
            $trxWallets = $this->trxWalletRepository->queryOrMemory();

            return new LoginResponse(
                sysPlayer: $sysPlayer,
                trxUnits: $trxUnits->values()->all(),
                trxItems: $trxItems->values()->all(),
                trxWallets: $trxWallets->values()->all(),
                loginBonusContents: $loginBonusContents,
            );
        });
    }
}
