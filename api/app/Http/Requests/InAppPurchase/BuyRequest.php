<?php

namespace App\Http\Requests\InAppPurchase;

use App\Http\Requests\_BaseRequest;

class BuyRequest extends _BaseRequest
{
    /**
     * リクエストの認可を判定
     */
    public function authorize(): bool
    {
        // 認証済みユーザーのみ
        return true;
    }

    /**
     * バリデーションルール
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mst_in_app_purchase_id' => ['required', 'integer', 'min:1'],
            'platform' => ['required', 'string', 'in:Apple,Google'],
            'billing_platform' => ['required', 'string', 'in:AppStore,GooglePlay,PayPal,Stripe'],
            'receipt' => ['required', 'string'],
            'transaction_id' => ['nullable', 'string'],
            'product_id' => ['required', 'string'],
        ];
    }

    /**
     * アプリ内課金商品IDを取得
     */
    public function getMstInAppPurchaseId(): int
    {
        return (int) $this->input('mst_in_app_purchase_id');
    }

    /**
     * プラットフォームを取得
     */
    public function getPlatform(): string
    {
        return $this->input('platform');
    }

    /**
     * 決済プラットフォームを取得
     */
    public function getBillingPlatform(): string
    {
        return $this->input('billing_platform');
    }

    /**
     * レシートを取得
     */
    public function getReceipt(): string
    {
        return $this->input('receipt');
    }

    /**
     * トランザクションIDを取得
     */
    public function getTransactionId(): ?string
    {
        return $this->input('transaction_id');
    }

    /**
     * プロダクトIDを取得
     */
    public function getProductId(): string
    {
        return $this->input('product_id');
    }

    /**
     * 認証済みプレイヤーIDを取得
     *
     * ミドルウェアで設定された値を取得
     */
    public function getAuthenticatedPlayerId(): ?int
    {
        $playerId = $this->attributes->get('authenticated_player_id');

        return $playerId ? (int) $playerId : null;
    }

    /**
     * カスタムバリデーションメッセージ
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mst_in_app_purchase_id.required' => 'mst_in_app_purchase_id is required',
            'mst_in_app_purchase_id.integer' => 'mst_in_app_purchase_id must be an integer',
            'mst_in_app_purchase_id.min' => 'mst_in_app_purchase_id must be at least 1',
            'platform.required' => 'platform is required',
            'platform.string' => 'platform must be a string',
            'platform.in' => 'platform must be one of: Apple, Google',
            'billing_platform.required' => 'billing_platform is required',
            'billing_platform.string' => 'billing_platform must be a string',
            'billing_platform.in' => 'billing_platform must be one of: AppStore, GooglePlay, PayPal, Stripe',
            'receipt.required' => 'receipt is required',
            'receipt.string' => 'receipt must be a string',
            'transaction_id.string' => 'transaction_id must be a string',
            'product_id.required' => 'product_id is required',
            'product_id.string' => 'product_id must be a string',
        ];
    }

    /**
     * バリデーション属性名のカスタマイズ
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'mst_in_app_purchase_id' => 'In-App Purchase ID',
            'platform' => 'Platform',
            'billing_platform' => 'Billing Platform',
            'receipt' => 'Receipt',
            'transaction_id' => 'Transaction ID',
            'product_id' => 'Product ID',
        ];
    }
}
