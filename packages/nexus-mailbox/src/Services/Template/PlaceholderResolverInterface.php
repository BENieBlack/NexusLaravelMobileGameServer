<?php

namespace NexusMailbox\Services\Template;

/**
 * PlaceholderResolverInterface
 *
 * プレースホルダーを解決するインターフェース
 *
 * テンプレート内の{key}形式のプレースホルダーを実際の値に置換するための
 * Resolver実装が実装すべきインターフェース
 *
 * 使用例:
 * ```php
 * class PlayerPlaceholder implements PlaceholderResolverInterface
 * {
 *     public function supports(string $key): bool
 *     {
 *         return in_array($key, ['player_name', 'player_level']);
 *     }
 *
 *     public function resolve(string $key, array $context): ?string
 *     {
 *         $player = $context['player'] ?? null;
 *         return match($key) {
 *             'player_name' => $player->getName(),
 *             'player_level' => (string)$player->getLevel(),
 *             default => null,
 *         };
 *     }
 * }
 * ```
 */
interface PlaceholderResolverInterface
{
    /**
     * プレースホルダーキーをサポートしているか
     *
     * @param  string  $key  プレースホルダーキー（例: 'player_name'）
     */
    public function supports(string $key): bool;

    /**
     * プレースホルダーを解決
     *
     * @param  string  $key  プレースホルダーキー
     * @param  array  $context  コンテキスト情報（player, alliance, battleなど）
     * @return string|null 解決した値、解決できない場合はnull
     */
    public function resolve(string $key, array $context): ?string;

    /**
     * サポートしているキーの一覧を取得
     *
     * @return array<string> サポートしているキーのリスト
     */
    public function supportedKeys(): array;
}
