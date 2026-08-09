<?php

namespace App\Http\Responses\Auth;

use App\Models\Sys\SysPlayer;
use App\Models\Sys\SysPlayerDevice;
use App\Models\Sys\SysPlayerToken;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use NexusAuth\DTOs\TokenDto;

/**
 * SignUpResponse
 *
 * サインアップAPIのレスポンス
 * sys_player, sys_player_device, sys_player_token の構造体と dto_token を返す
 */
class SignUpResponse implements Responsable
{
    /**
     * @param  SysPlayer  $sysPlayer  プレイヤー情報
     * @param  SysPlayerDevice  $sysPlayerDevice  デバイス情報
     * @param  SysPlayerToken  $sysPlayerToken  トークン情報
     * @param  TokenDto  $tokenDto  トークン情報DTO
     */
    public function __construct(
        public readonly SysPlayer $sysPlayer,
        public readonly SysPlayerDevice $sysPlayerDevice,
        public readonly SysPlayerToken $sysPlayerToken,
        public readonly TokenDto $tokenDto,
    ) {}

    /**
     * レスポンスを生成
     *
     * @param  Request  $request
     */
    public function toResponse($request): JsonResponse
    {
        return response()->json([
            'sys_player' => $this->sysPlayer->toArray(),
            'sys_player_device' => $this->sysPlayerDevice->toArray(),
            'sys_player_token' => $this->sysPlayerToken->toArray(),
            'dto_token' => $this->tokenDto->toArray(),
        ]);
    }
}
