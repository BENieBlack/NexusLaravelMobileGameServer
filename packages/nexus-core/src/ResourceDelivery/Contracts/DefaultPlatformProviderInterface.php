<?php

namespace Nexus\Core\ResourceDelivery\Contracts;

/**
 * DefaultPlatformProviderInterface
 *
 * 「いま処理しているリクエスト／文脈の platform」を供給するインターフェース
 * （ダイヤの付与で metadata に platform が無いときの既定値に使う）。
 *
 * パッケージ層は API のセッション（ApiSession）に依存できないので、
 * 実装は Application 層で行い、パッケージ層はこれに依存する。
 *
 * 契約: 例外を投げない。platform の文脈が無い経路（ジョブ・バッチ・CLI など）では
 * null を返し、呼び出し側が従来の既定値にフォールバックする。
 */
interface DefaultPlatformProviderInterface
{
    /**
     * 現在の処理の platform を返す
     *
     * @return string|null platform（Apple / Google）。文脈が無ければ null
     */
    public function findCurrentPlatform(): ?string;
}
