<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Sharding Configuration
    |--------------------------------------------------------------------------
    |
    | トランザクションデータベースのシャーディング設定
    |
    */

    'transaction' => [
        /*
         * シャード接続名のプレフィックス
         * 例: 'trx' の場合、'trx1', 'trx2', ... となる
         */
        'prefix' => 'trx',

        /*
         * シャードノードの設定
         * 'node_no' => 'connection_name' の形式で定義
         *
         * 新しいシャードを追加する場合は、以下を実施：
         * 1. このリストに新しいノードを追加
         * 2. config/database.php に対応する接続設定を追加
         * 3. .env に対応する環境変数を追加
         * 4. docker-compose.yml にデータベースコンテナを追加（必要に応じて）
         */
        'nodes' => [
            1 => 'trx1',
            2 => 'trx2',
            // 3 => 'trx3',  // 新しいシャードを追加する場合はコメント解除
            // 4 => 'trx4',
        ],
    ],

    /*
     * 設定から動的にシャード接続名のリストを取得
     *
     * @return array ['trx1', 'trx2', ...]
     */
    'get_shard_connections' => function () {
        return array_values(config('sharding.transaction.nodes', []));
    },
];
