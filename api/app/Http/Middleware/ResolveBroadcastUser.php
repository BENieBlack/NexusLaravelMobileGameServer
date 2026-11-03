<?php

namespace App\Http\Middleware;

use App\Persistence\ApiSession;
use Closure;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ResolveBroadcastUser
 *
 * /broadcasting/auth で、auth.token が認証したプレイヤーを $request->user() として見せる。
 *
 * プレイヤー認証は ApiSession で持ち、Laravel の認証ガードを使っていない。
 * 一方 Reverb（Pusher 方式）は $request->user() が無いと private / presence チャンネルを
 * 必ず拒否し、presence チャンネルではその ID を参加者の ID に使う。
 * そのためプレイヤーIDだけを持つ GenericUser を渡す。
 *
 * auth.token の後に置く。
 */
class ResolveBroadcastUser
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (ApiSession::hasSysPlayerId()) {
            $sysPlayerId = ApiSession::getSysPlayerId();
            $request->setUserResolver(fn () => new GenericUser(['id' => $sysPlayerId]));
        }

        return $next($request);
    }
}
