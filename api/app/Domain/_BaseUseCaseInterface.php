<?php

namespace App\Domain;

/**
 * _BaseUseCaseInterface
 *
 * すべてのUseCaseが実装すべきインターフェース
 *
 * ## 設計理念
 *
 * このインターフェースは最小限の制約のみを定義し、各UseCaseの柔軟性を保ちます。
 *
 * ### handle()メソッド
 * - **必須**: すべてのUseCaseで実装
 * - **シグネチャ**: 各UseCaseで独自に定義可能
 * - **戻り値**: 任意の型（Response、DTO、配列など）
 * - **責務**: ユースケースのメインロジックを実行
 *
 * ### validation()メソッド
 * - **推奨**: 実装を推奨するが強制ではない
 * - **シグネチャ**: 各UseCaseで独自に定義可能
 * - **戻り値**: void（例外をスローして失敗を通知）
 * - **責務**: ビジネスロジック実行前の事前検証
 *
 * ## 実装例
 *
 * ### 基本的な実装
 * ```php
 * class SomeUseCase extends _BaseUseCase
 * {
 *     public function handle(int $playerId, string $param): SomeResponse
 *     {
 *         return $this->executeWithTransaction(function () use ($playerId, $param) {
 *             // ビジネスロジック
 *             return new SomeResponse(...);
 *         });
 *     }
 * }
 * ```
 *
 * ### validationありの実装
 * ```php
 * class LevelUpUseCase extends _BaseUseCase
 * {
 *     public function validation(int $playerId, int $unitId, int $itemCount): void
 *     {
 *         // エンティティの存在確認
 *         $unit = $this->unitRepository->selectById($unitId);
 *         if (!$unit) {
 *             throw new NotFoundException("Unit not found");
 *         }
 *
 *         // 権限チェック
 *         if ($unit->getSysPlayerId() !== $playerId) {
 *             throw new UnauthorizedException("Unit does not belong to player");
 *         }
 *
 *         // ビジネスルール検証
 *         if ($itemCount < 1) {
 *             throw new ValidationException("Item count must be positive");
 *         }
 *     }
 *
 *     public function handle(int $playerId, int $unitId, int $itemCount): LevelUpResponse
 *     {
 *         // まずバリデーション
 *         $this->validation($playerId, $unitId, $itemCount);
 *
 *         return $this->executeWithTransaction(function () use ($playerId, $unitId, $itemCount) {
 *             // ビジネスロジック
 *             return new LevelUpResponse(...);
 *         });
 *     }
 * }
 * ```
 *
 * ### 読み取り専用の実装（トランザクション不要）
 * ```php
 * class ListUseCase extends _BaseUseCase
 * {
 *     public function handle(int $playerId): ListResponse
 *     {
 *         // トランザクション不要な読み取り処理
 *         $items = $this->repository->getList($playerId);
 *         return new ListResponse($items);
 *     }
 * }
 * ```
 *
 * ## ベストプラクティス
 *
 * 1. **handle()メソッドは明確なシグネチャを持つ**
 *    - パラメータの型と戻り値の型を明示
 *    - PHPDocで例外を文書化
 *
 * 2. **validation()は事前検証に使用**
 *    - データベースアクセスの最小化
 *    - handle()の最初に呼び出す
 *    - 失敗時は例外をスロー
 *
 * 3. **トランザクション管理は適切に**
 *    - 更新系処理: executeWithTransaction()を使用
 *    - 読み取り専用: トランザクション不要
 *
 * 4. **レスポンスオブジェクトを返す**
 *    - 専用のResponseクラスを作成
 *    - コントローラーとの責務分離
 */
interface _BaseUseCaseInterface
{
    /**
     * ユースケースのメイン処理
     *
     * 各UseCaseで独自のシグネチャを定義してください。
     *
     * @return mixed レスポンスオブジェクトやDTOなど
     */
    // public function handle(...$args): mixed;

    /**
     * バリデーション処理（オプション）
     *
     * ビジネスロジック実行前の事前検証を行います。
     * 実装は任意ですが、複雑なビジネスロジックの場合は推奨します。
     *
     * @return void
     *
     * @throws \Exception バリデーション失敗時
     */
    // public function validation(...$args): void;
}
