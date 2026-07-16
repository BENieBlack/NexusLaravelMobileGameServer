<?php

namespace App\Domain\MailBox\Services;

/**
 * PlaceholderResolverInterface
 *
 * プレースホルダーを解決するインターフェース
 */
interface PlaceholderResolverInterface
{
    /**
     * プレースホルダーキーをサポートしているか
     *
     * @param string $key
     * @return bool
     */
    public function supports(string $key): bool;

    /**
     * プレースホルダーを解決
     *
     * @param string $key
     * @param array $context コンテキスト情報
     * @return string|null
     */
    public function resolve(string $key, array $context): ?string;

    /**
     * サポートしているキーの一覧を取得
     *
     * @return array<string>
     */
    public function getSupportedKeys(): array;
}
