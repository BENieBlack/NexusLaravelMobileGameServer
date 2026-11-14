<?php

namespace Nexus\Core\Resource\DataTransferObjects;

/**
 * DiamondBalance
 *
 * 有償ダイヤモンド残高（platform 別）のデータ転送オブジェクト。
 * 無償ダイヤは trx_item の free_diamond で持つので、ここには含めない。
 */
class DiamondBalance
{
    /**
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  string  $platform  プラットフォーム（Apple, Google）
     * @param  int  $paidAmount  有償ダイヤモンド数
     */
    public function __construct(
        private int $sysPlayerId,
        private string $platform,
        private int $paidAmount,
    ) {}

    /**
     * プレイヤーIDを取得
     */
    public function getSysPlayerId(): int
    {
        return $this->sysPlayerId;
    }

    /**
     * プラットフォームを取得
     */
    public function getPlatform(): string
    {
        return $this->platform;
    }

    /**
     * 有償ダイヤモンド数を取得
     */
    public function getPaidAmount(): int
    {
        return $this->paidAmount;
    }

    /**
     * 有償ダイヤモンド数を設定
     */
    public function setPaidAmount(int $paidAmount): void
    {
        $this->paidAmount = $paidAmount;
    }

    /**
     * 配列からDTOを生成
     *
     * @param  array{sys_player_id: int, platform: string, paid_amount?: int}  $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            sysPlayerId: $data['sys_player_id'],
            platform: $data['platform'],
            paidAmount: $data['paid_amount'] ?? 0,
        );
    }

    /**
     * 配列に変換
     *
     * @return array{sys_player_id: int, platform: string, paid_amount: int}
     */
    public function toArray(): array
    {
        return [
            'sys_player_id' => $this->sysPlayerId,
            'platform' => $this->platform,
            'paid_amount' => $this->paidAmount,
        ];
    }
}
