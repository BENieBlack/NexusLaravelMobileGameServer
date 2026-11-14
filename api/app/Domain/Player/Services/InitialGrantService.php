<?php

namespace App\Domain\Player\Services;

use App\Domain\Player\Constants\InitialGrantConst;
use App\Domain\Player\Contracts\InitialGrantInterface;
use App\Domain\Stamina\Contracts\StaminaInitializerInterface;
use App\Repositories\Mst\MstPlayerLevelRepository;
use Nexus\Core\Resource\Constants\FreeDiamondConst;
use Nexus\Core\Resource\Services\DiamondService;
use Nexus\Core\Resource\Services\ItemService;
use Nexus\Core\ResourceDelivery\Contracts\CurrencyGranterInterface;

/**
 * InitialGrantService
 *
 * サインアップ時の初期データ付与。trx_diamond・trx_wallet・trx_item・trx_stamina に
 * 1 レコードずつ入れる（設計 home-player-info の db.md 8 節）。
 *
 * ResourceDeliveryService は使わず、各 Service を直接呼ぶ。ResourceDeliveryService は
 * ハンドラーの例外を握りつぶして続行するため、付与が失敗しても sign_up が成功し、
 * 初期データの無いプレイヤーが残ってしまう（設計 M1）。直接呼べば例外が
 * SignUpUseCase の executeWithTransaction まで届き、全接続がロールバックされる。
 */
class InitialGrantService implements InitialGrantInterface
{
    public function __construct(
        private readonly DiamondService $diamondService,
        private readonly CurrencyGranterInterface $currencyGranter,
        private readonly ItemService $itemService,
        private readonly StaminaInitializerInterface $staminaInitializer,
        private readonly MstPlayerLevelRepository $mstPlayerLevelRepository,
    ) {}

    /**
     * {@inheritDoc}
     *
     * @throws \RuntimeException レベル 1 の mst_player_level が無いとき
     */
    public function grant(int $sysPlayerId, string $platform): void
    {
        // 先に初期スタミナを確かめる。マスターが無い（配信前・未投入）ときは、既定値で
        // 続けず sign_up を失敗させ、デプロイの不備として検出する。
        // 何も書く前に落とすので、無駄な書き込みも起きない
        $initialStamina = $this->mstPlayerLevelRepository->findMaxStaminaForLevel(InitialGrantConst::INITIAL_LEVEL);

        if ($initialStamina === null) {
            throw new \RuntimeException(
                'mst_player_level に level '.InitialGrantConst::INITIAL_LEVEL.' が無いため、初期スタミナを決められません'
            );
        }

        // trx_diamond: 端末の platform の行を paid_amount = 0 で作る（有償だけの行。
        // 無償ダイヤは下の trx_item の free_diamond）
        $this->diamondService->addPaidDiamond($sysPlayerId, $platform, 0);

        // trx_wallet: Coin。mst_item.is_wallet の値に左右されないよう、通貨の付与口を直接呼ぶ
        $this->currencyGranter->grantCurrency(
            $sysPlayerId,
            InitialGrantConst::COIN_MST_ITEM_ID,
            InitialGrantConst::COIN_AMOUNT,
            0,
            null,
        );

        // trx_item: 無償ダイヤ。パッケージ版の addItem を直接呼び、is_wallet の振り分けを通さない
        // （確実に trx_item へ入れるため）
        $this->itemService->addItem(
            $sysPlayerId,
            FreeDiamondConst::MST_ITEM_ID,
            InitialGrantConst::FREE_DIAMOND_AMOUNT,
        );

        // trx_stamina: level 1 の最大値で type = normal の行を作る（last_recovery_at は作成時刻）
        $this->staminaInitializer->initializeNormalStamina($sysPlayerId, $initialStamina);
    }
}
