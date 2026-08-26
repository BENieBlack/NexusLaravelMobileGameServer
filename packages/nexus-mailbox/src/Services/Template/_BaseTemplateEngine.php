<?php

namespace NexusMailbox\Services\Template;

/**
 * _BaseTemplateEngine
 *
 * メールテンプレートのプレースホルダー置換エンジン（基底クラス）
 *
 * {key}形式のプレースホルダーを実際の値に置換する汎用テンプレートエンジン
 * Application層でこのクラスを継承し、ゲーム固有のResolverを登録して使用する
 *
 * Template Method Pattern:
 * - テンプレートのパースと置換ロジックは共通実装（このクラス）
 * - プレースホルダーResolverはサブクラスで登録（拡張ポイント）
 *
 * 使用方法:
 * ```php
 * class TemplateEngine extends _BaseTemplateEngine
 * {
 *     public function __construct()
 *     {
 *         parent::__construct();
 *         $this->registerResolver(new PlayerPlaceholder());
 *         $this->registerResolver(new AlliancePlaceholder());
 *     }
 * }
 *
 * $engine = new TemplateEngine();
 * $result = $engine->render('Hello {player_name}!', [], ['player' => $player]);
 * // "Hello John!"
 * ```
 *
 * Placeholder形式:
 * - {key} - 波括弧で囲まれた英数字とアンダースコアのキー
 * - 例: {player_name}, {alliance_level}, {item_amount}
 *
 * 置換優先順位:
 * 1. カスタムパラメータ（$params）
 * 2. Resolver（登録順）
 * 3. 未解決の場合は設定に従う（empty/keep）
 */
abstract class _BaseTemplateEngine
{
    /**
     * プレースホルダーResolverのリスト
     *
     * @var array<PlaceholderResolverInterface>
     */
    protected array $resolvers = [];

    /**
     * 未解決プレースホルダーの処理方法
     * 'empty' = 空文字に置換, 'keep' = そのまま残す
     */
    protected string $unresolvedBehavior = 'empty';

    /**
     * Resolverを登録
     */
    public function registerResolver(PlaceholderResolverInterface $resolver): void
    {
        $this->resolvers[] = $resolver;
    }

    /**
     * 未解決プレースホルダーの処理方法を設定
     *
     * @param  string  $behavior  'empty' or 'keep'
     */
    public function setUnresolvedBehavior(string $behavior): void
    {
        $this->unresolvedBehavior = $behavior;
    }

    /**
     * テンプレートをレンダリング
     *
     * @param  string  $template  テンプレート文字列
     * @param  array<string, mixed>  $params  カスタムパラメータ（最優先）
     * @param  array<string, mixed>  $context  コンテキスト情報（player, alliance, battle, etc.）
     * @return string レンダリング結果
     */
    public function render(string $template, array $params = [], array $context = []): string
    {
        // プレースホルダーを抽出
        $placeholders = $this->parsePlaceholders($template);

        // 各プレースホルダーを置換
        foreach ($placeholders as $placeholder) {
            $key = $placeholder['key'];
            $fullMatch = $placeholder['match'];

            // 置換値を解決
            $value = $this->resolvePlaceholder($key, $params, $context);

            // 置換
            if ($value !== null) {
                $template = str_replace($fullMatch, $value, $template);
            } elseif ($this->unresolvedBehavior === 'empty') {
                $template = str_replace($fullMatch, '', $template);
            }
            // 'keep'の場合はそのまま残す（何もしない）
        }

        return $template;
    }

    /**
     * プレースホルダーをパース
     *
     * @return array<array{key: string, match: string}>
     */
    protected function parsePlaceholders(string $template): array
    {
        $placeholders = [];

        // {key} 形式のプレースホルダーを抽出
        preg_match_all('/{([a-z_]+)}/i', $template, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $placeholders[] = [
                'match' => $match[0],  // {player_name}
                'key' => $match[1],    // player_name
            ];
        }

        return $placeholders;
    }

    /**
     * プレースホルダーを解決
     *
     * @param  array<string, mixed>  $params  カスタムパラメータ
     * @param  array<string, mixed>  $context  コンテキスト
     */
    protected function resolvePlaceholder(string $key, array $params, array $context): ?string
    {
        // 1. カスタムパラメータを最優先で確認
        if (isset($params[$key])) {
            return (string) $params[$key];
        }

        // 2. Resolverで解決
        foreach ($this->resolvers as $resolver) {
            if ($resolver->supports($key)) {
                $value = $resolver->resolve($key, $context);
                if ($value !== null) {
                    return $value;
                }
            }
        }

        // 3. 解決できない
        return null;
    }

    /**
     * サポートしているプレースホルダーの一覧を取得
     *
     * @return array<string, array<string>>
     */
    public function supportedPlaceholders(): array
    {
        $supported = [];

        foreach ($this->resolvers as $resolver) {
            $className = (new \ReflectionClass($resolver))->getShortName();
            $supported[$className] = $resolver->supportedKeys();
        }

        return $supported;
    }
}
