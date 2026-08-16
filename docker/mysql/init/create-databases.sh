#!/bin/bash
#
# 各MySQLコンテナが自分の担当するデータベースを作る。
#
# /docker-entrypoint-initdb.d/ に置かれたスクリプトは、データディレクトリが
# 空のとき（＝ボリュームの初回作成時）に一度だけ実行される。
# 実行時点ではまだ外部からの接続を受け付けていないため、
# コンテナが起動した時点でデータベースは必ず存在している。
#
# 作成対象は docker-compose.yml の DB_NAMES 環境変数で渡す（スペース区切り）。
# 開発用と、api/phpunit.xml が参照するテスト用の2つを持つコンテナがある。
#
# Laravelのマイグレーションでは代替できない。マイグレーションは接続先の
# データベースが既に存在していることが前提であり、かつこのプロジェクトは
# データベースごとにMySQLサーバーが分かれているため、
# ある接続から別のサーバーのデータベースを作ることもできない。

set -e

if [ -z "${DB_NAMES:-}" ]; then
  echo "[init] DB_NAMES が未設定のためデータベースを作成しません。"
  exit 0
fi

for db in $DB_NAMES; do
  echo "[init] creating database: ${db}"
  mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" -e \
    "CREATE DATABASE IF NOT EXISTS \`${db}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
done
