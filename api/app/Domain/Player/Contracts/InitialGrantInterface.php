<?php

namespace App\Domain\Player\Contracts;

/**
 * InitialGrantInterface
 *
 * サインアップ時の初期データ付与の公開 API（Auth コンテキストが使う）。
 */
interface InitialGrantInterface
{
    /**
     * 新規プレイヤーに初期データを付与する
     *
     * 呼び出し側（SignUpUseCase）のトランザクションの中で呼ぶ。
     * 付与の失敗は握りつぶさず例外で投げ、呼び出し側のトランザクションをロールバックさせる。
     *
     * @param  int  $sysPlayerId  新規プレイヤーID（ApiSession に載せた後であること）
     * @param  string  $platform  trx_diamond の行の platform（Apple / Google）
     */
    public function grant(int $sysPlayerId, string $platform): void;
}
