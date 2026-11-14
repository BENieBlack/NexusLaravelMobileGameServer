<?php

namespace App\Domain\MstConfig\Contracts;

/**
 * MstConfigReaderInterface
 *
 * 他のコンテキストが mst_config の値を読むための公開 API。
 *
 * 他コンテキストは MstConfig の内部（MstConfigKey など）を参照できない
 * （ModuleBoundaryTest）。設定ごとに読み出しメソッドを足し、
 * キー・既定値・型検査・警告ログは実装（MstConfigService）の中に閉じ込める。
 */
interface MstConfigReaderInterface
{
    /**
     * スタミナが 1 回復する間隔（秒）を取得する
     *
     * 行が無い・型違い・整数として読めない・1 未満のときは、既定値（300）を返して警告ログを残す。
     *
     * @return int 常に 1 以上の整数
     */
    public function findStaminaRecoveryIntervalSeconds(): int;
}
