<?php

namespace App\Domain\MailBox\Services;

use App\Domain\MailBox\Services\Placeholders\AlliancePlaceholder;
use App\Domain\MailBox\Services\Placeholders\BattlePlaceholder;
use App\Domain\MailBox\Services\Placeholders\PlayerPlaceholder;
use App\Domain\MailBox\Services\Placeholders\SystemPlaceholder;

/**
 * TemplateEngine
 *
 * メールテンプレートのプレースホルダー置換エンジン
 */
class TemplateEngine
{
    /**
     * プレースホルダーResolverのリスト
     *
     * @var array<PlaceholderResolverInterface>
     */
    private array $resolvers = [];

    /**
     * 未解決プレースホルダーの処理方法
     * 'empty' = 空文字に置換, 'keep' = そのまま残す
     */
    private string $unresolvedBehavior = 'empty';

    /**
     * コンストラクタ
     */
    public function __construct()
    {
        // デフォルトのResolverを登録
        $this->registerResolver(new PlayerPlaceholder());
        $this->registerResolver(new SystemPlaceholder());
        $this->registerResolver(new AlliancePlaceholder());
        $this->registerResolver(new BattlePlaceholder());
    }

    /**
     * Resolverを登録
     *
     * @param PlaceholderResolverInterface $resolver
     * @return void
     */
    public function registerResolver(PlaceholderResolverInterface $resolver): void
    {
        $this->resolvers[] = $resolver;
    }

    /**
     * 未解決プレースホルダーの処理方法を設定
     *
     * @param string $behavior 'empty' or 'keep'
     * @return void
     */
    public function setUnresolvedBehavior(string $behavior): void
    {
        $this->unresolvedBehavior = $behavior;
    }

    /**
     * テンプレートをレンダリング
     *
     * @param string $template テンプレート文字列
     * @param array $params カスタムパラメータ（最優先）
     * @param array $context コンテキスト情報（player, alliance, battle, etc.）
     * @return string
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
     * @param string $template
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
     * @param string $key
     * @param array $params カスタムパラメータ
     * @param array $context コンテキスト
     * @return string|null
     */
    protected function resolvePlaceholder(string $key, array $params, array $context): ?string
    {
        // 1. カスタムパラメータを最優先で確認
        if (isset($params[$key])) {
            return (string)$params[$key];
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
    public function getSupportedPlaceholders(): array
    {
        $supported = [];

        foreach ($this->resolvers as $resolver) {
            $className = (new \ReflectionClass($resolver))->getShortName();
            $supported[$className] = $resolver->getSupportedKeys();
        }

        return $supported;
    }
}
