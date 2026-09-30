<?php

namespace NexusLevel\Services;

/**
 * _BaseLevelService
 *
 * レベルシステムの共通ロジックを提供する抽象基底クラス
 * Template Method Patternを使用して、経験値加算・レベルアップの共通処理を実装
 *
 * 共通処理:
 * - 経験値加算
 * - レベル計算（累積経験値ベース）
 * - 最大レベル制限
 * - レベルアップ判定
 *
 * ドメイン固有処理（サブクラスでフック可能）:
 * - レベルアップ時の追加処理（例: プレイヤーのスタミナ全回復）
 * - エンティティの取得処理
 * - Repository更新処理
 *
 * 使用方法:
 * ```php
 * class PlayerLevelService extends _BaseLevelService
 * {
 *     protected function findEntity(mixed $id): object { ... }
 *     protected function resolveRarity(object $entity): ?string { return null; }
 *     protected function resolveCurrentLevel(object $entity): int { ... }
 *     protected function resolveCurrentExp(object $entity): int { ... }
 *     protected function calculateNewLevel(string|null $rarity, int $totalExp): int { ... }
 *     protected function findMaxLevel(string|null $rarity): int { ... }
 *     protected function updateEntity(object $entity, int $level, int $exp): void { ... }
 *     protected function onLevelUp(object $entity, int $beforeLevel, int $afterLevel): void { ... }
 * }
 * ```
 */
abstract class _BaseLevelService
{
    /**
     * 経験値を加算し、レベルアップ処理を行う（Template Method）
     *
     * @param  mixed  $id  エンティティID（プレイヤーID、装備ID、ユニットIDなど）
     * @param  int  $exp  加算する経験値
     * @return array{
     *   is_leveled_up: bool,
     *   before_level: int,
     *   after_level: int,
     *   total_exp: int
     * } レベルアップ結果
     *
     * @throws \Exception エンティティが存在しない場合
     */
    public function addExp(mixed $id, int $exp): array
    {
        // 1. エンティティを取得
        $entity = $this->findEntity($id);

        // 2. 現在の状態を取得
        $rarity = $this->resolveRarity($entity);
        $beforeLevel = $this->resolveCurrentLevel($entity);
        $currentExp = $this->resolveCurrentExp($entity);

        // 3. 経験値を加算
        $newTotalExp = $currentExp + $exp;

        // 4. 新しいレベルを計算
        $newLevel = $this->calculateNewLevel($rarity, $newTotalExp);

        // 5. 最大レベルを超えないように制限
        $maxLevel = $this->findMaxLevel($rarity);
        $afterLevel = min($newLevel, $maxLevel);

        // 6. レベルアップ判定
        $isLeveledUp = ($afterLevel > $beforeLevel);

        // 7. エンティティを更新
        $this->updateEntity($entity, $afterLevel, $newTotalExp);

        // 8. レベルアップ時のフック処理
        if ($isLeveledUp) {
            $this->onLevelUp($entity, $beforeLevel, $afterLevel);
        }

        // 9. 結果を返す
        return [
            'is_leveled_up' => $isLeveledUp,
            'before_level' => $beforeLevel,
            'after_level' => $afterLevel,
            'total_exp' => $newTotalExp,
        ];
    }

    /**
     * 次のレベルまでに必要な経験値を取得（共通処理）
     *
     * @param  string|null  $rarity  レアリティ（プレイヤーの場合はnull）
     * @param  int  $currentLevel  現在のレベル
     * @param  int  $currentExp  現在の累積経験値
     * @return int|null 必要な経験値（最大レベルの場合はnull or 0）
     */
    abstract public function calcExpToNextLevel(?string $rarity, int $currentLevel, int $currentExp): ?int;

    // ========================================
    // Abstract Methods（サブクラスで実装必須）
    // ========================================

    /**
     * エンティティを取得
     *
     * @param  mixed  $id  エンティティID
     * @return object エンティティオブジェクト
     *
     * @throws \Exception エンティティが存在しない場合
     */
    abstract protected function findEntity(mixed $id): object;

    /**
     * エンティティのレアリティを取得
     *
     * @param  object  $entity  エンティティオブジェクト
     * @return string|null レアリティ（プレイヤーの場合はnull）
     */
    abstract protected function resolveRarity(object $entity): ?string;

    /**
     * エンティティの現在レベルを取得
     *
     * @param  object  $entity  エンティティオブジェクト
     * @return int 現在レベル
     */
    abstract protected function resolveCurrentLevel(object $entity): int;

    /**
     * エンティティの現在経験値を取得
     *
     * @param  object  $entity  エンティティオブジェクト
     * @return int 現在経験値
     */
    abstract protected function resolveCurrentExp(object $entity): int;

    /**
     * 累積経験値から新しいレベルを計算
     *
     * @param  string|null  $rarity  レアリティ（プレイヤーの場合はnull）
     * @param  int  $totalExp  累積経験値
     * @return int 計算されたレベル
     */
    abstract protected function calculateNewLevel(?string $rarity, int $totalExp): int;

    /**
     * 最大レベルを取得
     *
     * @param  string|null  $rarity  レアリティ（プレイヤーの場合はnull）
     * @return int 最大レベル
     */
    abstract protected function findMaxLevel(?string $rarity): int;

    /**
     * エンティティのレベルと経験値を更新
     *
     * @param  object  $entity  エンティティオブジェクト
     * @param  int  $level  新しいレベル
     * @param  int  $exp  新しい経験値
     * @return void
     */
    abstract protected function updateEntity(object $entity, int $level, int $exp): void;

    // ========================================
    // Hook Methods（サブクラスでオーバーライド可能）
    // ========================================

    /**
     * レベルアップ時のフック処理
     *
     * デフォルトでは何もしない。
     * サブクラスでオーバーライドして、ドメイン固有の処理を実装する。
     *
     * 例: プレイヤーの場合はスタミナを全回復
     *
     * @param  object  $entity  エンティティオブジェクト
     * @param  int  $beforeLevel  レベルアップ前のレベル
     * @param  int  $afterLevel  レベルアップ後のレベル
     * @return void
     */
    protected function onLevelUp(object $entity, int $beforeLevel, int $afterLevel): void
    {
        // デフォルトでは何もしない
    }
}
