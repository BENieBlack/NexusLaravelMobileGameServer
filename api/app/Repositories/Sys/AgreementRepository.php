<?php

namespace App\Repositories\Sys;

use App\Persistence\ApiSession;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Support\CustomCollection;
use NexusAgreement\Models\SysAgreement;
use NexusAgreement\Models\TrxAgreement;
use NexusAgreement\Repositories\AgreementRepositoryInterface;

/**
 * AgreementRepository
 *
 * 利用規約リポジトリの実装
 * sys_agreement は sysDB、trx_agreement は trxDB を使用する
 *
 * @extends _BaseSysRepository<SysAgreement>
 */
class AgreementRepository extends _BaseSysRepository implements AgreementRepositoryInterface
{
    protected string $modelClass = SysAgreement::class;

    /**
     * 必須規約のうち指定日時以前に公開されたものを全件取得する（sysDB）
     *
     * @return CustomCollection<int, SysAgreement>
     */
    public function findRequiredPublishedSince(DateTimeInterface $since): CustomCollection
    {
        /** @var CustomCollection<int, SysAgreement> */
        return new CustomCollection(
            $this->selectWithoutCache()
                ->where('is_required', true)
                ->where('published_at', '<=', $since->format('Y-m-d H:i:s'))
                ->get()
                ->all()
        );
    }

    /**
     * プレイヤーが同意済みの sys_agreement_id 一覧を返す（trxDB）
     *
     * @return array<int, int>
     */
    public function findConsentedIds(int $sysPlayerId): array
    {
        return TrxAgreement::on($this->resolveConsentConnection())
            ->where('sys_player_id', $sysPlayerId)
            ->pluck('sys_agreement_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * 同意記録を insert する（trxDB）
     *
     * trx_agreement は insert only のため、DB::table()->insert() で直接 insert する。
     */
    public function insertConsent(int $sysPlayerId, int $sysAgreementId, CarbonImmutable $consentedAt): void
    {
        $connection = $this->resolveConsentConnection();

        DB::connection($connection)
            ->table('trx_agreement')
            ->insert([
                'sys_player_id' => $sysPlayerId,
                'sys_agreement_id' => $sysAgreementId,
                'consented_at' => $consentedAt->format('Y-m-d H:i:s'),
                'created_at' => $consentedAt->format('Y-m-d H:i:s'),
            ]);
    }

    /**
     * trx_agreement 用の接続名を返す
     */
    private function resolveConsentConnection(): string
    {
        if (static::hasSysPlayerId()) {
            return ApiSession::resolveConnectionName('trx');
        }

        return 'trx1';
    }
}
