<?php

namespace App\Domain;

use App\Traits\UseCaseTrait;

/**
 * _BaseUseCase
 *
 * すべてのUseCaseの基底クラス
 *
 * ## 提供する機能
 *
 * ### トランザクション管理（UseCaseTraitより）
 * - executeWithTransaction(): トランザクション内でビジネスロジックを実行
 * - 自動的にsys/trx/log接続でトランザクション管理
 * - QueryManagerによるバッチクエリ実行
 *
 * ## 使用方法
 *
 * ```php
 * class SomeUseCase extends _BaseUseCase
 * {
 *     public function exec($param): Response
 *     {
 *         return $this->executeWithTransaction(function () use ($param) {
 *             // ビジネスロジック
 *             return new Response(...);
 *         });
 *     }
 *
 *     // validation()メソッドの実装を推奨
 *     public function validation($param): void
 *     {
 *         // バリデーション処理
 *     }
 * }
 * ```
 *
 * ## 設計ガイドライン
 *
 * ### handle()メソッド
 * - 各UseCaseで独自のシグネチャを定義
 * - トランザクション管理が必要な場合は`executeWithTransaction()`を使用
 * - 読み取り専用の処理はトランザクション不要
 *
 * ### validation()メソッド（推奨）
 * - ビジネスロジック実行前のバリデーション
 * - handle()内で最初に呼び出すことを推奨
 * - 存在確認、権限チェック、ビジネスルール検証など
 * - 必要に応じて各UseCaseで実装（強制ではない）
 */
abstract class _BaseUseCase
{
    use UseCaseTrait;

    /**
     * ユースケースのメイン処理
     *
     * 各UseCaseで独自のシグネチャとロジックを実装する
     *
     * @param  mixed  ...$args
     * @return mixed
     */
    // abstract public function exec(...$args): mixed;

    /**
     * バリデーション処理（オプション）
     *
     * 実装は任意だが、以下のケースでは実装を推奨：
     * - エンティティの存在確認
     * - 権限チェック
     * - ビジネスルールの検証
     * - データ整合性チェック
     *
     * @param  mixed  ...$args
     * @return void
     *
     * @throws \Exception バリデーション失敗時
     */
    // public function validation(...$args): void {}
}
