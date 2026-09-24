<?php

namespace App\Http\Middleware;

use App\Persistence\ApiSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ResolveLanguage
 *
 * Accept-Language からリクエストの言語を決め、ApiSessionに載せる。
 *
 * - `ja-JP,ja;q=0.9,en;q=0.8` のような重み付きの値を解釈する
 * - config('language.supported') に無い言語は config('language.default') にフォールバックする
 * - ヘッダーが無い場合も既定の言語を使う
 *
 * 参照側は ApiSession::getLanguage() を使う。
 */
class ResolveLanguage
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $language = $this->resolve($request);

        ApiSession::setLanguage($language);
        app()->setLocale($language);

        return $next($request);
    }

    /**
     * サポートしている言語のうち、クライアントが最も優先するものを返す
     */
    private function resolve(Request $request): string
    {
        /** @var list<string> $supported */
        $supported = config('language.supported', []);
        $default = (string) config('language.default');

        if ($supported === []) {
            return $default;
        }

        if ($request->header('Accept-Language') === null) {
            return $default;
        }

        // 先頭を既定にしておくと、一致が無いときにgetPreferredLanguage()が既定を返す
        $preferred = $request->getPreferredLanguage(
            array_values(array_unique([$default, ...$supported]))
        );

        if ($preferred === null) {
            return $default;
        }

        // Symfonyは 'zh-TW' を 'zh_TW' に正規化して返すため、設定値の表記に戻す
        foreach ($supported as $language) {
            if (strcasecmp(str_replace('_', '-', $preferred), $language) === 0) {
                return $language;
            }
        }

        return $default;
    }
}
