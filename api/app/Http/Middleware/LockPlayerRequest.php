<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * LockPlayerRequest
 *
 * 同じプレイヤーの書き込みリクエストを、1つずつ順番に処理する。
 *
 * trx は「読む → メモリで計算 → 絶対値でUPDATE」で書くため、同じプレイヤーの2つのリクエストが
 * 同時に走ると、後から書いたほうが先の更新を上書きする（ロストアップデート）。
 * updated_resources も、メモリ上の値をそのまま返すので、DBと食い違う。
 * 行ロック（SELECT ... FOR UPDATE）は、読み取りがトランザクションの前にあり、
 * 複数のテーブルをまたぐため漏れやすい。プレイヤー単位でリクエストを直列にするほうが確実。
 *
 * GET（読み取り）は対象外。待っても取れなければ 409 を返し、クライアントに再試行させる。
 */
class LockPlayerRequest
{
    /** ロックを持てる最大時間（秒）。リクエストが落ちてもこの時間で自動的に外れる */
    private const LOCK_SECONDS = 30;

    /** ロックが取れるまで待つ最大時間（秒）。config('security.player_lock.wait_seconds') で変えられる */
    private const DEFAULT_WAIT_SECONDS = 5;

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $playerId = $request->attributes->get('authenticated_player_id');

        if (! $playerId || $request->isMethod('GET')) {
            return $next($request);
        }

        $lock = Cache::lock("player_request:{$playerId}", self::LOCK_SECONDS);

        try {
            $lock->block((int) config('security.player_lock.wait_seconds', self::DEFAULT_WAIT_SECONDS));
        } catch (LockTimeoutException) {
            return response()->json([
                'error_code' => 40900,
                'message' => 'Another request is being processed. Please retry later.',
            ], 409);
        }

        try {
            return $next($request);
        } finally {
            $lock->release();
        }
    }
}
