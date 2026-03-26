<?php

namespace App\Domain;

/**
 * _BaseUseCaseInterface
 * 
 * すべてのUseCaseが実装すべきインターフェース
 * 
 * ## 必須メソッド
 * 
 * ### handle()
 * ユースケースのメイン処理
 * - 各UseCaseで独自のシグネチャを定義
 * - 戻り値の型も自由に設定可能
 * 
 * ### validation()（推奨・規約ベース）
 * ビジネスロジック実行前のバリデーション
 * - handle()実行前に呼び出すことを推奨
 * - 各UseCaseで適切な型定義を行うこと
 * - バリデーション不要な場合は実装しなくても良い
 * 
 * #### validation()の実装例
 * ```php
 * public function validation(int $sysPlayerId, int $trxUnitId, string $mstItemId, int $useCount): void
 * {
 *     // ユニットの存在確認
 *     // アイテムの所持数確認
 *     // etc...
 * }
 * ```
 * 
 * ## 設計理念
 * 
 * このインターフェースは最小限の制約のみを定義します。
 * - 各UseCaseの柔軟性を保つため、メソッドシグネチャは強制しない
 * - validation()は規約として推奨するが、型安全性のため強制しない
 * - ドキュメントとコードレビューで品質を担保する
 */
interface _BaseUseCaseInterface
{
    // 各UseCaseは独自のhandle()メソッドを実装する
    // public function handle(...): mixed;
    
    // 各UseCaseは独自のvalidation()メソッドを実装することを推奨
    // public function validation(...): void;
}
