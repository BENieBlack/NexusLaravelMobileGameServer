<?php

namespace App\Domain\Player\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Player\Responses\MeResponse;
use App\Exceptions\SystemDataException;
use App\Models\Sys\SysPlayer;
use App\Repositories\Sys\SysPlayerRepository;

/**
 * MeUseCase
 *
 * 認証済みプレイヤーの情報取得ユースケース
 */
class MeUseCase extends _BaseUseCase
{
    public function __construct(
        private readonly SysPlayerRepository $sysPlayerRepository,
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
     * 認証済みプレイヤーの情報を取得
     *
     * @throws \Exception
     */
    public function handle(int $sysPlayerId): MeResponse
    {
        // バリデーション実行（存在を確かめたプレイヤーが返る）
        $sysPlayer = $this->validation($sysPlayerId);

        return new MeResponse(
            myId: $sysPlayer->getMyId(),
            name: $sysPlayer->getName(),
        );
    }
}
