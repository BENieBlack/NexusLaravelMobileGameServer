<?php

namespace App\Domain\Chat\Support;

use App\Repositories\Sys\SysPlayerRepository;

/**
 * ChatPlayerNameResolver
 *
 * チャットの表示名を解決する
 *
 * メッセージとメンバー行には送信時点の名前を焼き込む。
 * 後から改名しても過去の発言の表示が変わらないようにするため、
 * 参照ではなく値で持つ。
 */
class ChatPlayerNameResolver
{
    public function __construct(
        private readonly SysPlayerRepository $sysPlayerRepository,
    ) {}

    public function resolve(int $sysPlayerId): string
    {
        return $this->sysPlayerRepository->selectById($sysPlayerId)?->getName() ?? '';
    }
}
