#!/bin/bash
#
# Environment Setup Script for NexusLaravelMobileGameServer
#
# 開発環境をゼロから構築する。
#
# WARNING: 既存のDockerボリューム（＝DBの中身）をすべて削除します。
#          初回構築、または完全にリセットしたいときだけ使ってください。
#
# 実行内容:
#   1. Dockerコンテナの再作成と起動
#   2. MySQLの起動完了を待つ
#   3. 全データベースの作成（sys, mst, adm, tol, trx1..N, log1..N）
#   4. 依存パッケージのインストール（Composerはコンテナ内、npmはホスト）
#   5. APP_KEYの生成
#   6. マイグレーションとシードの実行
#
# Usage:
#   ./command/setup.sh
#
# Requirements:
#   - Docker / Docker Compose
#   - プロジェクトルートに .env（無ければ .env.example から自動生成する）
#   - Node.js（任意。無い場合はアセットビルドをスキップする）
#
# ホストにPHP・Composerは不要です。コンテナ内のPHP 8.4を使います。
#

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

GREEN='\033[0;32m'
YELLOW='\033[0;33m'
RED='\033[0;31m'
NC='\033[0m'

function success_message() { echo -e "${GREEN}$1${NC}"; }
function warn_message() { echo -e "${YELLOW}$1${NC}"; }
function error_message() { echo -e "${RED}$1${NC}"; }

# .env から値を取り出す
function get_env_value() {
  local env_file=$1
  local key=$2
  if [ -f "$env_file" ]; then
    grep "^${key}=" "$env_file" | head -1 | cut -d '=' -f2- | tr -d '"' | tr -d "'"
  else
    echo ""
  fi
}

cd "$PROJECT_ROOT"

# ============================================================================
# Step 0: 前提の確認
# ============================================================================
if ! command -v docker > /dev/null 2>&1; then
  error_message "docker が見つかりません。Docker をインストールしてください。"
  exit 1
fi

# Compose v2 (docker compose) を優先し、無ければ v1 (docker-compose) にフォールバック
if docker compose version > /dev/null 2>&1; then
  COMPOSE=(docker compose)
elif command -v docker-compose > /dev/null 2>&1; then
  COMPOSE=(docker-compose)
else
  error_message "Docker Compose が見つかりません。"
  exit 1
fi
echo "Using Compose command: ${COMPOSE[*]}"

ROOT_ENV_FILE="${PROJECT_ROOT}/.env"
if [ ! -f "$ROOT_ENV_FILE" ]; then
  warn_message ".env が無いため .env.example からコピーします。"
  cp "${PROJECT_ROOT}/.env.example" "$ROOT_ENV_FILE"
fi

APP_NAME=$(get_env_value "$ROOT_ENV_FILE" "APP_NAME")
APP_ENV=$(get_env_value "$ROOT_ENV_FILE" "APP_ENV")
MYSQL_ROOT_PASSWORD=$(get_env_value "$ROOT_ENV_FILE" "MYSQL_ROOT_PASSWORD")
SHARD_COUNT=$(get_env_value "$ROOT_ENV_FILE" "DB_TRX_SHARDS")

if [ -z "$APP_NAME" ] || [ -z "$APP_ENV" ]; then
  error_message "APP_NAME または APP_ENV が $ROOT_ENV_FILE にありません。"
  exit 1
fi
MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD:-root}"
SHARD_COUNT="${SHARD_COUNT:-2}"

echo "APP_NAME: ${APP_NAME}"
echo "APP_ENV:  ${APP_ENV}"
echo "Shards:   ${SHARD_COUNT}"

# ============================================================================
# Step 1: コンテナの再作成
# ============================================================================
echo "Setting up Docker containers..."
warn_message "WARNING: 既存のコンテナとボリュームをすべて削除します。"
"${COMPOSE[@]}" down --volumes --remove-orphans
"${COMPOSE[@]}" up -d
success_message "Docker containers are up and running."

# ============================================================================
# Step 2: 対象データベースの決定
# ============================================================================
# "コンテナ名:DB接尾辞" の組。DB名は {APP_NAME}-{APP_ENV}-{接尾辞}
DB_TARGETS=("db-sys:sys" "db-mst:mst" "db-adm:adm" "db-tol:tol")
for i in $(seq 1 "$SHARD_COUNT"); do
  DB_TARGETS+=("db-trx${i}:trx${i}" "db-log${i}:log${i}")
done

# docker-compose.yml が定義していないシャードを指定していないか確認する
for target in "${DB_TARGETS[@]}"; do
  container="${target%%:*}"
  if ! "${COMPOSE[@]}" ps --services | grep -qx "$container"; then
    error_message "コンテナ ${container} が docker-compose.yml に定義されていません。"
    error_message "DB_TRX_SHARDS=${SHARD_COUNT} に対してコンテナ定義が不足しています。"
    exit 1
  fi
done

# ============================================================================
# Step 3: MySQLの起動完了を待つ
# ============================================================================
# 初回起動はデータディレクトリの初期化が走るため、固定のsleepでは足りない。
echo "Waiting for MySQL containers to be ready..."
READY_TIMEOUT=180
for target in "${DB_TARGETS[@]}"; do
  container="${target%%:*}"
  waited=0
  until docker exec "$container" mysqladmin ping -uroot -p"${MYSQL_ROOT_PASSWORD}" --silent > /dev/null 2>&1; do
    if [ "$waited" -ge "$READY_TIMEOUT" ]; then
      error_message "${container} が ${READY_TIMEOUT} 秒以内に起動しませんでした。"
      error_message "docker logs ${container} で原因を確認してください。"
      exit 1
    fi
    sleep 2
    waited=$((waited + 2))
  done
  echo "  ${container} ready (${waited}s)"
done
success_message "All MySQL containers are ready."

# ============================================================================
# Step 4: データベースの作成
# ============================================================================
echo "Creating databases..."
for target in "${DB_TARGETS[@]}"; do
  container="${target%%:*}"
  suffix="${target##*:}"
  database="${APP_NAME}-${APP_ENV}-${suffix}"
  docker exec "$container" mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" \
    -e "CREATE DATABASE IF NOT EXISTS \`${database}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2> /dev/null
  echo "  ${database}"
done
success_message "Databases created."

# ============================================================================
# Step 5: APP_KEY の生成
# ============================================================================
# .env はコンテナに読み取り専用でマウントされているため、
# artisan key:generate ではなくホスト側で書き込む。
if [ -z "$(get_env_value "$ROOT_ENV_FILE" "APP_KEY")" ]; then
  echo "Generating APP_KEY..."
  generated_key="base64:$(openssl rand -base64 32)"
  tmp_env="$(mktemp)"
  awk -v key="$generated_key" '/^APP_KEY=/ { print "APP_KEY=" key; next } { print }' \
    "$ROOT_ENV_FILE" > "$tmp_env"
  mv "$tmp_env" "$ROOT_ENV_FILE"
  success_message "APP_KEY generated."
else
  echo "APP_KEY already set. Skipped."
fi

# ============================================================================
# Step 6: 依存パッケージのインストール
# ============================================================================
# Composerはコンテナ内で実行する（ホストのPHPバージョンに依存させない）。
echo "Installing PHP dependencies (in containers)..."
"${COMPOSE[@]}" exec -T api-php composer install --no-interaction
"${COMPOSE[@]}" exec -T tool-php composer install --no-interaction
success_message "PHP dependencies installed."

# npmはコンテナに含まれていないためホストで実行する。
# ビルド成果物はTool管理画面の表示にのみ使うので、無ければスキップする。
if command -v npm > /dev/null 2>&1; then
  echo "Building assets..."
  (cd "${PROJECT_ROOT}/api" && npm install --no-audit --no-fund && npm run build)
  (cd "${PROJECT_ROOT}/tool" && rm -f package-lock.json && npm install --no-audit --no-fund && npm run build)
  success_message "Assets built."
else
  warn_message "npm が見つからないためアセットビルドをスキップしました。"
  warn_message "APIの開発には影響しません。Tool画面を使う場合は Node.js を入れて再実行してください。"
fi

# ============================================================================
# Step 7: マイグレーション
# ============================================================================
# sys/mst のマイグレーションは Schema::connection() で対象を固定しているが、
# trx/log は既定接続を使う。そのため接続とパスを必ず明示して実行する。
# 引数なしの `php artisan migrate` は既定接続（sqlite）に流れてしまうので使わない。
#
# マイグレーションは api/database/migrations/{group} と
# packages/*/database/migrations/{group} に分かれて置かれている。
#
# --path は base_path() からの相対パスとして解決される（--realpath を付けない限り
# 絶対パスは base_path() を前置されて存在しないパスになり、黙って無視される）。
# そのため ../packages/... の形で渡すこと。
function migrate_group() {
  local connection=$1
  local group=$2
  "${COMPOSE[@]}" exec -T api-php sh -c "
    set -e
    args=''
    for p in database/migrations/${group} ../packages/*/database/migrations/${group}; do
      [ -d \"\$p\" ] && args=\"\$args --path=\$p\"
    done
    php artisan migrate --database=${connection} --force \$args
  "
}

echo "Running API migrations (sys)..."
migrate_group sys sys
echo "Running API migrations (mst)..."
migrate_group mst mst

echo "Running TrxDB migrations for all shards..."
"${COMPOSE[@]}" exec -T api-php php artisan trx:migrate --force

echo "Running LogDB migrations for all shards..."
"${COMPOSE[@]}" exec -T api-php php artisan pitr:migrate --force

echo "Running Tool migrations..."
"${COMPOSE[@]}" exec -T tool-php php artisan migrate --path=database/migrations/adm --database=admin --force
"${COMPOSE[@]}" exec -T tool-php php artisan migrate --path=database/migrations/tol --database=tool --force
success_message "Migrations completed."

# ============================================================================
# Step 8: シード
# ============================================================================
# シャーディング設定とマスターデータが無いとAPIは1本も通らないため、
# 開発環境では初期データまで入れて完了とする。
echo "Seeding API data..."
"${COMPOSE[@]}" exec -T api-php php artisan db:seed --force

echo "Seeding Tool data..."
"${COMPOSE[@]}" exec -T tool-php php artisan db:seed --class=AdminAccountSeeder --force
success_message "Seeding completed."

echo ""
success_message "Environment setup completed successfully."
echo "  API:  http://localhost:8090"
echo "  Tool: http://localhost:8091"
echo ""
echo "テストを実行する場合: ./command/sail api test"
