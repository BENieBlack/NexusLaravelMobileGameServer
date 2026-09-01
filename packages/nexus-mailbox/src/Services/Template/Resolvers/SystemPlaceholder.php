<?php

namespace NexusMailbox\Services\Template\Resolvers;

use Nexus\Core\Utilities\ClockUtility;
use NexusMailbox\Services\Template\PlaceholderResolverInterface;

/**
 * SystemPlaceholder
 *
 * システム情報のプレースホルダー
 *
 * サポートするプレースホルダー:
 * - {timestamp} - 現在の日時（Y-m-d H:i:s形式）
 * - {date} - 現在の日付（Y-m-d形式）
 * - {time} - 現在の時刻（H:i:s形式）
 * - {server_name} - サーバー名
 * - {version} - アプリバージョン
 *
 * 使用例:
 * ```php
 * $resolver = new SystemPlaceholder('My Game Server', '1.0.0');
 * $engine->registerResolver($resolver);
 * $result = $engine->render('Server: {server_name} v{version}');
 * // "Server: My Game Server v1.0.0"
 * ```
 */
class SystemPlaceholder implements PlaceholderResolverInterface
{
    /**
     * サポートしているキー
     */
    private const SUPPORTED_KEYS = [
        'timestamp',
        'date',
        'time',
        'server_name',
        'version',
    ];

    /**
     * コンストラクタ
     *
     * @param  string  $serverName  サーバー名
     * @param  string  $version  アプリバージョン
     */
    public function __construct(
        private readonly string $serverName = 'Game Server',
        private readonly string $version = '1.0.0',
    ) {}

    /**
     * {@inheritDoc}
     */
    public function supports(string $key): bool
    {
        return in_array($key, self::SUPPORTED_KEYS, true);
    }

    /**
     * {@inheritDoc}
     *
     * @param  array<string, mixed>  $context
     */
    public function resolve(string $key, array $context): ?string
    {
        $now = ClockUtility::now();

        return match ($key) {
            'timestamp' => $now->format('Y-m-d H:i:s'),
            'date' => $now->format('Y-m-d'),
            'time' => $now->format('H:i:s'),
            'server_name' => $this->serverName,
            'version' => $this->version,
            default => null,
        };
    }

    /**
     * {@inheritDoc}
     */
    public function supportedKeys(): array
    {
        return self::SUPPORTED_KEYS;
    }
}
