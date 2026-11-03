<?php

namespace App\Repositories\Sys;

use App\Models\Sys\SysPlayerExternalAccount;
use Nexus\Core\Support\CustomCollection;

/**
 * SysPlayerExternalAccountRepository
 *
 * 外部アカウント（Apple / Google）との紐付けの永続化のみを担当
 *
 * @extends _BaseSysRepository<SysPlayerExternalAccount>
 */
class SysPlayerExternalAccountRepository extends _BaseSysRepository
{
    protected string $modelClass = SysPlayerExternalAccount::class;

    /**
     * ログイン中プレイヤーの紐付けを全て取得
     *
     * @return CustomCollection<int, SysPlayerExternalAccount>
     */
    public function selectListByPlayerId(int $sysPlayerId): CustomCollection
    {
        /** @var CustomCollection<int, SysPlayerExternalAccount> $accounts */
        $accounts = $this->queryOrMemory()
            ->filter(fn (SysPlayerExternalAccount $account) => $account->getSysPlayerId() === $sysPlayerId)
            ->sortBy(fn (SysPlayerExternalAccount $account) => $account->getProvider())
            ->values();

        return $accounts;
    }

    /**
     * ログイン中プレイヤーの、指定した種類の紐付けを取得
     *
     * @param  string  $provider  apple / google
     */
    public function selectByPlayerIdAndProvider(int $sysPlayerId, string $provider): ?SysPlayerExternalAccount
    {
        return $this->selectListByPlayerId($sysPlayerId)
            ->first(fn (SysPlayerExternalAccount $account) => $account->getProvider() === $provider);
    }

    /**
     * 外部アカウントから紐付けを取得（キャッシュを通さない）
     *
     * 他のプレイヤーの紐付けも見るため、自分スコープを使えない
     *
     * @param  string  $provider  apple / google
     */
    public function selectByProviderUserId(string $provider, string $providerUserId): ?SysPlayerExternalAccount
    {
        /** @var SysPlayerExternalAccount|null */
        return $this->selectWithoutCache()
            ->where('provider', $provider)
            ->where('provider_user_id', $providerUserId)
            ->first();
    }

    /**
     * 紐付けを登録する
     *
     * @param  string  $provider  apple / google
     */
    public function insertExternalAccount(int $sysPlayerId, string $provider, string $providerUserId): SysPlayerExternalAccount
    {
        $sysPlayerExternalAccount = new SysPlayerExternalAccount([
            'sys_player_id' => $sysPlayerId,
            'provider' => $provider,
            'provider_user_id' => $providerUserId,
        ]);
        $sysPlayerExternalAccount->exists = false;

        $this->setModel($sysPlayerExternalAccount);

        return $sysPlayerExternalAccount;
    }

    /**
     * 紐付けを解除する
     */
    public function deleteExternalAccount(SysPlayerExternalAccount $sysPlayerExternalAccount): void
    {
        $this->hardDeleteModel($sysPlayerExternalAccount);
    }
}
