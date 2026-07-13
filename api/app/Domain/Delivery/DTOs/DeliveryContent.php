<?php

namespace App\Domain\Delivery\DTOs;

use App\Domain\Delivery\Constants\DeliveryConst;
use App\Domain\Delivery\Enums\DeliveryStatus;
use App\Domain\Delivery\Enums\DeliveryResultReason;
use Ramsey\Uuid\Uuid;

/**
 * DeliveryContent
 *
 * 配布物のデータ構造
 * Item, Unit, Equipment, Diamond, Wallet通貨などを統一的に表現
 *
 * - 配送ステータス（未送信、送信済み、配布済み）
 * - 変換履歴（元のデータと変換後のデータを両方保持）
 * - 配送失敗理由（上限超過、メールボックス送信など）
 * - ログ用のbefore/after情報
 */
class DeliveryContent
{
    /** @var string DeliveryManagerでの管理のために使用する一意のID */
    private string $uniqueId;

    /** @var DeliveryStatus 配送ステータス */
    private DeliveryStatus $status;

    /** @var DeliveryResultReason 変換理由 */
    private DeliveryResultReason $conversionReason;

    /** @var DeliveryResultReason 配送失敗理由 */
    private DeliveryResultReason $failureReason;

    /** @var array|null 元のデータ（変換前） */
    private ?array $originalData = null;

    /** @var int ログ用：付与前のリソース量 */
    private int $beforeAmount = 0;

    /** @var int ログ用：付与後のリソース量 */
    private int $afterAmount = 0;

    /**
     * @param string $type 配布物タイプ (item, unit, equipment, diamond, wallet)
     * @param string $id マスターID (mst_item_id, mst_unit_id等)
     * @param int $amount 数量
     * @param string|null $expireAt 有効期限（Y-m-d H:i:s形式、Wallet通貨用、NULLは無期限）
     * @param array|null $metadata 追加情報（Unit: grade/level, Equipment: 初期値等）
     */
    public function __construct(
        private string $type,
        private string $id,
        private int $amount,
        private ?string $expireAt = null,
        private ?array $metadata = null,
    ) {
        $this->uniqueId = $this->generateUniqueId();
        $this->status = DeliveryStatus::PENDING;
        $this->conversionReason = DeliveryResultReason::NONE;
        $this->failureReason = DeliveryResultReason::NONE;
    }

    /**
     * 配布物タイプを取得
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * マスターIDを取得
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * 数量を取得
     */
    public function getAmount(): int
    {
        return $this->amount;
    }

    /**
     * 有効期限を取得
     * 
     * @return string|null Y-m-d H:i:s形式の日時文字列
     */
    public function getExpireAt(): ?string
    {
        return $this->expireAt;
    }

    /**
     * メタデータを取得
     */
    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    /**
     * 一意のIDを生成
     */
    private function generateUniqueId(): string
    {
        return (string) Uuid::uuid4();
    }

    /**
     * 一意のIDを取得
     */
    public function getUniqueId(): string
    {
        return $this->uniqueId;
    }

    /**
     * 配送ステータスを取得
     */
    public function getStatus(): DeliveryStatus
    {
        return $this->status;
    }

    /**
     * 送信完了としてマーク（配送処理完了時に呼び出し）
     * 即時配布の場合はRECEIVED、メールボックス送信の場合はDELIVEREDにする
     */
    public function markAsSendComplete(): void
    {
        // メールボックス送信などで即時配布できなかった場合はDELIVERED
        if ($this->hasFailed()) {
            $this->status = DeliveryStatus::DELIVERED;
        } else {
            // 即時配布の場合はRECEIVED
            $this->status = DeliveryStatus::RECEIVED;
        }
    }

    /**
     * メールボックス送信済みとしてマーク
     */
    public function markAsDelivered(): void
    {
        $this->status = DeliveryStatus::DELIVERED;
    }

    /**
     * 受取済みとしてマーク
     */
    public function markAsReceived(): void
    {
        $this->status = DeliveryStatus::RECEIVED;
    }

    /**
     * 送信完了かどうか（配送処理が完了しているか）
     */
    public function isSendComplete(): bool
    {
        return $this->status === DeliveryStatus::DELIVERED || $this->status === DeliveryStatus::RECEIVED;
    }

    /**
     * 配送に失敗したかどうか（メールボックス送信などで即時配布されなかった）
     */
    public function hasFailed(): bool
    {
        return $this->failureReason !== DeliveryResultReason::NONE;
    }

    /**
     * 変換されたかどうか
     */
    public function isConverted(): bool
    {
        return $this->conversionReason !== DeliveryResultReason::NONE;
    }

    /**
     * 有効なコンテンツかどうか（数量が1以上）
     */
    public function isValid(): bool
    {
        return $this->amount > 0;
    }

    /**
     * 変換理由を取得
     */
    public function getConversionReason(): DeliveryResultReason
    {
        return $this->conversionReason;
    }

    /**
     * 配送失敗理由を取得
     */
    public function getFailureReason(): DeliveryResultReason
    {
        return $this->failureReason;
    }

    /**
     * 配送失敗理由を設定
     */
    public function setFailureReason(DeliveryResultReason $reason): void
    {
        $this->failureReason = $reason;
    }

    /**
     * コンテンツを変換（元のデータを保存してから新しいデータに置き換える）
     *
     * @param string $newType 新しいタイプ
     * @param string $newId 新しいID
     * @param int $newAmount 新しい数量
     * @param DeliveryResultReason $reason 変換理由
     */
    public function convertTo(string $newType, string $newId, int $newAmount, DeliveryResultReason $reason): void
    {
        // 元のデータを保存
        if ($this->originalData === null) {
            $this->originalData = [
                'type' => $this->type,
                'id' => $this->id,
                'amount' => $this->amount,
            ];
        }

        // 新しいデータに置き換え
        $this->type = $newType;
        $this->id = $newId;
        $this->amount = $newAmount;
        $this->conversionReason = $reason;
    }

    /**
     * 元のデータを取得（変換前のデータ）
     */
    public function getOriginalData(): ?array
    {
        return $this->originalData;
    }

    /**
     * ログ用：付与前のリソース量を設定
     */
    public function setBeforeAmount(int $amount): void
    {
        $this->beforeAmount = $amount;
    }

    /**
     * ログ用：付与後のリソース量を設定
     */
    public function setAfterAmount(int $amount): void
    {
        $this->afterAmount = $amount;
    }

    /**
     * ログ用：付与前のリソース量を取得
     */
    public function getBeforeAmount(): int
    {
        return $this->beforeAmount;
    }

    /**
     * ログ用：付与後のリソース量を取得
     */
    public function getAfterAmount(): int
    {
        return $this->afterAmount;
    }

    /**
     * 配列からDeliveryContentを生成
     *
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: $data['type'],
            id: $data['id'],
            amount: $data['amount'],
            expireAt: $data['expire_at'] ?? null,
            metadata: $data['metadata'] ?? null,
        );
    }

    /**
     * 配列に変換
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'id' => $this->id,
            'amount' => $this->amount,
            'expire_at' => $this->expireAt,
            'metadata' => $this->metadata,
            'status' => $this->status->value,
            'conversion_reason' => $this->conversionReason->value,
            'failure_reason' => $this->failureReason->value,
            'original_data' => $this->originalData,
        ];
    }

    /**
     * Itemタイプの配送データを作成
     *
     * @param string $mstItemId
     * @param int $amount
     * @return self
     */
    public static function item(string $mstItemId, int $amount): self
    {
        return new self(
            type: DeliveryConst::CONTENT_TYPE_ITEM,
            id: $mstItemId,
            amount: $amount,
        );
    }

    /**
     * Unitタイプの配送データを作成
     *
     * @param string $mstUnitId
     * @param int $amount
     * @param int|null $grade
     * @param int|null $level
     * @return self
     */
    public static function unit(string $mstUnitId, int $amount, ?int $grade = null, ?int $level = null): self
    {
        $metadata = [];
        if ($grade !== null) {
            $metadata['grade'] = $grade;
        }
        if ($level !== null) {
            $metadata['level'] = $level;
        }

        return new self(
            type: DeliveryConst::CONTENT_TYPE_UNIT,
            id: $mstUnitId,
            amount: $amount,
            metadata: empty($metadata) ? null : $metadata,
        );
    }

    /**
     * Equipmentタイプの配送データを作成
     *
     * @param string $mstEquipmentId
     * @param int $amount
     * @return self
     */
    public static function equipment(string $mstEquipmentId, int $amount): self
    {
        return new self(
            type: DeliveryConst::CONTENT_TYPE_EQUIPMENT,
            id: $mstEquipmentId,
            amount: $amount,
        );
    }

    /**
     * Diamondタイプの配送データを作成
     *
     * @param int $amount
     * @param bool $isPaid 有償ダイヤモンドか（falseの場合は無償）
     * @return self
     */
    public static function diamond(int $amount, bool $isPaid = false): self
    {
        return new self(
            type: DeliveryConst::CONTENT_TYPE_DIAMOND,
            id: 'diamond', // Diamondは固定ID
            amount: $amount,
            metadata: ['is_paid' => $isPaid],
        );
    }

    /**
     * Walletタイプの配送データを作成
     *
     * @param string $mstItemId 通貨アイテムID (gold, event_coin等)
     * @param int $amount
     * @param string|null $expireAt 有効期限（Y-m-d H:i:s形式）
     * @return self
     */
    public static function wallet(string $mstItemId, int $amount, ?string $expireAt = null): self
    {
        return new self(
            type: DeliveryConst::CONTENT_TYPE_WALLET,
            id: $mstItemId,
            amount: $amount,
            expireAt: $expireAt,
        );
    }
}
