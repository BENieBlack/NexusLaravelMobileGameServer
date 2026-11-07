<?php

namespace App\Domain\Player\UseCases;

use App\Domain\_BaseUseCase;
use App\Domain\Player\Responses\MeResponse;
use App\Exceptions\SystemDataException;
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
     * @throws SystemDataException プレイヤーが存在しない場合
     */
    public function validation(int $sysPlayerId): void
    {
        // プレイヤー情報の存在確認
        $sysPlayer = $this->sysPlayerRepository->selectById($sysPlayerId);

        if (! $sysPlayer) {
            throw SystemDataException::generic('sys_player', $sysPlayerId);
        }
    }

    /**
     * 認証済みプレイヤーの情報を取得
     *
     * @throws \Exception
     */
    public function handle(int $sysPlayerId): MeResponse
    {
        // バリデーション実行
        $this->validation($sysPlayerId);

        // プレイヤー情報を取得（バリデーション済み）
        $sysPlayer = $this->sysPlayerRepository->selectById($sysPlayerId)
            ?? throw new \LogicException('プレイヤーが見つかりません');

        return new MeResponse(
            myId: $sysPlayer->getMyId(),
            name: $sysPlayer->getName(),
        );
    }
}
