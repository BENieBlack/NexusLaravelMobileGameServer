<?php

namespace NexusAgreement\Models;

use Nexus\Core\Models\Trx\_BaseTrx;

/**
 * TrxAgreementConsent Model
 *
 * プレイヤーの利用規約同意記録テーブル（trx_agreement_consent）
 * insert only — 同意記録は不変の監査ログとして扱う
 * updated_at カラムなし
 *
 * @property int $sys_player_id
 * @property int $sys_agreement_id
 * @property string $consented_at
 * @property ?string $created_at
 */
class TrxAgreementConsent extends _BaseTrx
{
    /**
     * updated_at カラムなし（insert only）
     */
    public const UPDATED_AT = null;

    protected $table = 'trx_agreement_consent';

    /** @var list<string> */
    protected array $selectKeys = ['sys_player_id'];

    protected $primaryKey = ['sys_player_id', 'sys_agreement_id'];

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'sys_player_id',
        'sys_agreement_id',
        'consented_at',
        'created_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'sys_player_id' => 'integer',
        'sys_agreement_id' => 'integer',
    ];

    /**
     * is_delete カラムなし（insert only のため論理削除不要）
     */
    protected bool $excludesSoftDeleted = false;

    public function getSysPlayerId(): int
    {
        return (int) $this->getAttribute('sys_player_id');
    }

    public function setSysPlayerId(int $sysPlayerId): void
    {
        $this->setAttribute('sys_player_id', $sysPlayerId);
    }

    public function getSysAgreementId(): int
    {
        return (int) $this->getAttribute('sys_agreement_id');
    }

    public function setSysAgreementId(int $sysAgreementId): void
    {
        $this->setAttribute('sys_agreement_id', $sysAgreementId);
    }

    public function getConsentedAt(): string
    {
        return (string) $this->getAttribute('consented_at');
    }

    public function setConsentedAt(string $consentedAt): void
    {
        $this->setAttribute('consented_at', $consentedAt);
    }

    /**
     * @return array<string, mixed>
     */
    public function toResponseArray(): array
    {
        return [
            'sys_agreement_id' => $this->getSysAgreementId(),
            'consented_at' => $this->getConsentedAt(),
        ];
    }
}
