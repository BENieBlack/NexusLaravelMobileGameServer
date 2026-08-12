<?php

namespace App\Domain\Mailbox\Services\Placeholders;

use NexusMailbox\Services\Template\PlaceholderResolverInterface;

/**
 * PlayerPlaceholder
 *
 * プレイヤー情報のプレースホルダー
 */
class PlayerPlaceholder implements PlaceholderResolverInterface
{
    /**
     * サポートしているキー
     */
    private const SUPPORTED_KEYS = [
        'player_name',
        'player_id',
        'player_level',
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
        $player = $context['player'] ?? null;

        if ($player === null) {
            return null;
        }

        return match ($key) {
            'player_name' => $player->name ?? $player->getName() ?? null,
            'player_id' => (string) ($player->id ?? $player->getSysPlayerId() ?? null),
            'player_level' => (string) ($player->level ?? $player->getLevel() ?? null),
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
