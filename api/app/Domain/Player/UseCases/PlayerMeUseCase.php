<?php

namespace App\Domain\Player\UseCases;

use App\Domain\_BaseUseCase;
use App\Exceptions\SystemDataException;
use App\Http\Responses\Player\MeResponse;
use App\Repositories\Sys\SysPlayerRepository;

/**
 * PlayerMeUseCase
 * 
 * 認証済みプレイヤーの情報取得ユースケース
 */
class PlayerMeUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly SysPlayerRepository $sysPlayerRepository,
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
     * 認証済みプレイヤーの情報を取得
     *
     * @param int $sysPlayerId
     * @return MeResponse
     * @throws \Exception
     */
    public function exec(int $sysPlayerId): MeResponse
    {
        // バリデーション実行
        $this->validation($sysPlayerId);
        
        // プレイヤー情報を取得（バリデーション済み）
        $sysPlayer = $this->sysPlayerRepository->selectById($sysPlayerId);

        return new MeResponse(
            myId: $sysPlayer->getMyId(),
            name: $sysPlayer->getName(),
        );
    }
}
