<?php

namespace App\Domain\MailBox\Services\Placeholders;

use App\Domain\MailBox\Services\PlaceholderResolverInterface;
use Carbon\Carbon;

/**
 * SystemPlaceholder
 *
 * システム情報のプレースホルダー
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
     * {@inheritDoc}
     */
    public function supports(string $key): bool
    {
        return in_array($key, self::SUPPORTED_KEYS, true);
    }

    /**
     * {@inheritDoc}
     */
    public function resolve(string $key, array $context): ?string
    {
        return match($key) {
            'timestamp' => Carbon::now()->toDateTimeString(),
            'date' => Carbon::now()->toDateString(),
            'time' => Carbon::now()->toTimeString(),
            'server_name' => config('app.name', 'Game Server'),
            'version' => config('app.version', '1.0.0'),
            default => null,
        };
    }

    /**
     * {@inheritDoc}
     */
    public function getSupportedKeys(): array
    {
        return self::SUPPORTED_KEYS;
    }
}
