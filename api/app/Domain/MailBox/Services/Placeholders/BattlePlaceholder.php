<?php

namespace App\Domain\MailBox\Services\Placeholders;

use NexusMailbox\Services\Template\PlaceholderResolverInterface;

/**
 * BattlePlaceholder
 *
 * 戦闘情報のプレースホルダー
 */
class BattlePlaceholder implements PlaceholderResolverInterface
{
    /**
     * サポートしているキー
     */
    private const SUPPORTED_KEYS = [
        'battle_result',
        'enemy_name',
        'damage_dealt',
        'damage_received',
        'troops_lost',
        'troops_killed',
        'battle_time',
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
        $battle = $context['battle'] ?? null;

        if ($battle === null) {
            return null;
        }

        return match ($key) {
            'battle_result' => $battle->result ?? $battle->getResult() ?? null,
            'enemy_name' => $battle->enemy_name ?? $battle->getEnemyName() ?? null,
            'damage_dealt' => (string) ($battle->damage_dealt ?? $battle->getDamageDealt() ?? null),
            'damage_received' => (string) ($battle->damage_received ?? $battle->getDamageReceived() ?? null),
            'troops_lost' => (string) ($battle->troops_lost ?? $battle->getTroopsLost() ?? null),
            'troops_killed' => (string) ($battle->troops_killed ?? $battle->getTroopsKilled() ?? null),
            'battle_time' => $battle->battle_time ?? $battle->getBattleTime() ?? null,
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
