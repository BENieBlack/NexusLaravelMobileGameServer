#!/bin/bash
#
# Deploy Script for NexusLaravelMobileGameServer
#
# 最新のコードを取り込み、API / TOOL を利用できる状態にする。
# DBの中身は消さない（初期構築は setup.sh）。何度流しても安全なように作ってある。
#
# 実行内容:
#   1. git pull（fast-forward のみ。ローカルに変更があれば中止する）
#   2. Dockerイメージの再ビルドとコンテナの起動（api / tool / queue / reverb / nginx）
#   3. 依存パッケージのインストール（Composer はコンテナ内、npm はホスト）
#   4. マイグレーション（api: sys / mst / trx / log、tool: adm / tol）
#   5. キャッシュの破棄、キューワーカーと Reverb の再起動
#   6. API / TOOL のヘルスチェック（利用可能になるまで待つ）
#
# Usage:
#   ./command/deploy.sh [options]
#
# Options:
#   --skip-pull     git pull を行わない（取り込み済みのコードをそのまま反映する）
#   --no-dev        Composer の開発用パッケージを入れない（本番向け）
#   --skip-assets   npm のインストールとビルドを行わない
#   --skip-migrate  マイグレーションを行わない
#   -h, --help      このヘルプを表示する
#
# Environment:
#   API_URL   APIのヘルスチェック先（既定: http://localhost:8090/up）
#   TOOL_URL  TOOLのヘルスチェック先（既定: http://localhost:8091/up）
#   HEALTH_TIMEOUT  ヘルスチェックの待ち時間（秒。既定: 120）
#
# マスターデータやシードは流さない（本番のデータを上書きしないため）。
# 必要なときは make seed を別に実行すること。
#

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

GREEN='\033[0;32m'
YELLOW='\033[0;33m'
RED='\033[0;31m'
NC='\033[0m'

function step_message() { echo -e "\n${GREEN}==> $1${NC}"; }
function success_message() { echo -e "${GREEN}$1${NC}"; }
function warn_message() { echo -e "${YELLOW}$1${NC}"; }
function error_message() { echo -e "${RED}$1${NC}" >&2; }

SKIP_PULL=0
NO_DEV=0
SKIP_ASSETS=0
SKIP_MIGRATE=0

for arg in "$@"; do
  case "$arg" in
    --skip-pull) SKIP_PULL=1 ;;
    --no-dev) NO_DEV=1 ;;
    --skip-assets) SKIP_ASSETS=1 ;;
    --skip-migrate) SKIP_MIGRATE=1 ;;
    -h|--help) sed -n '2,36p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *) error_message "不明なオプション: $arg（--help で一覧を表示）"; exit 1 ;;
  esac
done

API_URL="${API_URL:-http://localhost:8090/up}"
TOOL_URL="${TOOL_URL:-http://localhost:8091/up}"
HEALTH_TIMEOUT="${HEALTH_TIMEOUT:-120}"

# どの段階で止まったかを分かるようにする
CURRENT_STEP="準備"
trap 'error_message "デプロイに失敗しました（段階: ${CURRENT_STEP}）。ここまでの変更は戻していません。原因を直して、もう一度実行してください。"' ERR

cd "$PROJECT_ROOT"

# ============================================================================
# Step 0: 前提の確認
# ============================================================================
CURRENT_STEP="前提の確認"
for command_name in docker git curl; do
  if ! command -v "$command_name" > /dev/null 2>&1; then
    error_message "${command_name} が見つかりません。"
    exit 1
  fi
done

# Compose v2 (docker compose) を優先し、無ければ v1 (docker-compose) にフォールバック
if docker compose version > /dev/null 2>&1; then
  COMPOSE=(docker compose)
elif command -v docker-compose > /dev/null 2>&1; then
  COMPOSE=(docker-compose)
else
  error_message "Docker Compose が見つかりません。"
  exit 1
fi

if [ ! -f "${PROJECT_ROOT}/.env" ]; then
  error_message "プロジェクトルートに .env がありません。初回は ./command/setup.sh を実行してください。"
  exit 1
fi

# ============================================================================
# Step 1: 最新のコードを取り込む
# ============================================================================
CURRENT_STEP="git pull"
if [ "$SKIP_PULL" -eq 1 ]; then
  warn_message "git pull をスキップします（--skip-pull）。"
else
  step_message "最新のコードを取り込みます（git pull）"

  if ! git diff --quiet || ! git diff --cached --quiet; then
    error_message "コミットしていない変更があるため中止します。退避（git stash）するか、--skip-pull を付けてください。"
    git status --short
    exit 1
  fi

  BRANCH="$(git rev-parse --abbrev-ref HEAD)"
  if [ "$BRANCH" = "HEAD" ]; then
    error_message "ブランチではなくコミットを直接チェックアウトしています。ブランチに切り替えてください。"
    exit 1
  fi

  BEFORE="$(git rev-parse --short HEAD)"
  # fast-forward のみ。マージコミットや履歴の書き換えを、デプロイが勝手に作らない
  git pull --ff-only
  AFTER="$(git rev-parse --short HEAD)"
  echo "ブランチ ${BRANCH}: ${BEFORE} -> ${AFTER}"
fi

# ============================================================================
# Step 2: Dockerイメージのビルドとコンテナの起動
# ============================================================================
CURRENT_STEP="Docker のビルドと起動"
step_message "Dockerイメージを作り直して、コンテナを起動します"
"${COMPOSE[@]}" up -d --build

# ============================================================================
# Step 3: 依存パッケージ
# ============================================================================
CURRENT_STEP="依存パッケージのインストール"
step_message "Composer の依存をインストールします"
COMPOSER_ARGS=(--no-interaction --no-progress --optimize-autoloader)
if [ "$NO_DEV" -eq 1 ]; then
  COMPOSER_ARGS+=(--no-dev)
fi
"${COMPOSE[@]}" exec -T api-php composer install "${COMPOSER_ARGS[@]}"
"${COMPOSE[@]}" exec -T tool-php composer install "${COMPOSER_ARGS[@]}"

CURRENT_STEP="アセットのビルド"
if [ "$SKIP_ASSETS" -eq 1 ]; then
  warn_message "アセットのビルドをスキップします（--skip-assets）。"
elif command -v npm > /dev/null 2>&1; then
  step_message "アセットをビルドします（npm）"
  # package-lock.json の通りに入れる（npm install だとロックを書き換えうる）
  (cd "${PROJECT_ROOT}/api" && npm ci --no-audit --no-fund && npm run build)
  (cd "${PROJECT_ROOT}/tool" && npm ci --no-audit --no-fund && npm run build)
else
  warn_message "npm が見つからないため、アセットのビルドをスキップしました。"
fi

# ============================================================================
# Step 4: マイグレーション
# ============================================================================
# sys/mst のマイグレーションは Schema::connection() で対象を固定しているが、
# trx/log は既定接続を使う。そのため接続とパスを必ず明示して実行する。
# 引数なしの `php artisan migrate` は既定接続（sqlite）に流れてしまうので使わない。
#
# --path は base_path() からの相対パスとして解決される。そのため ../packages/... の形で渡す。
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

CURRENT_STEP="マイグレーション"
if [ "$SKIP_MIGRATE" -eq 1 ]; then
  warn_message "マイグレーションをスキップします（--skip-migrate）。"
else
  step_message "マイグレーションを実行します"
  echo "API (sys)..."
  migrate_group sys sys
  echo "API (mst)..."
  migrate_group mst mst
  echo "API (trx: 全シャード)..."
  "${COMPOSE[@]}" exec -T api-php php artisan trx:migrate --force
  echo "API (log: 全シャード)..."
  "${COMPOSE[@]}" exec -T api-php php artisan pitr:migrate --force
  echo "TOOL (adm / tol)..."
  "${COMPOSE[@]}" exec -T tool-php php artisan migrate --path=database/migrations/adm --database=admin --force
  "${COMPOSE[@]}" exec -T tool-php php artisan migrate --path=database/migrations/tol --database=tool --force
fi

# ============================================================================
# Step 5: キャッシュとワーカー
# ============================================================================
CURRENT_STEP="キャッシュの破棄"
step_message "キャッシュを破棄し、ワーカーを再起動します"
# 古いキャッシュ（設定・ルート・ビュー・イベント）を捨てて、新しいコードを読ませる。
# php artisan optimize（config:cache）は使わない: api の設定（sharding.get_shard_connections）に
# クロージャがあり、設定をキャッシュ用に書き出せずに失敗するため
for service in api-php tool-php; do
  "${COMPOSE[@]}" exec -T "$service" php artisan optimize:clear
done

CURRENT_STEP="ワーカーの再起動"
# キューワーカーは起動したままコードを持ち続けるため、新しいコードを読ませるには再起動が要る。
# queue:restart は処理中のジョブを終えてから止まる（restart: unless-stopped で立ち上がり直す）
"${COMPOSE[@]}" exec -T api-php php artisan queue:restart
"${COMPOSE[@]}" restart api-reverb

# ============================================================================
# Step 6: ヘルスチェック
# ============================================================================
CURRENT_STEP="ヘルスチェック"
function wait_until_healthy() {
  local name=$1
  local url=$2
  local waited=0
  echo -n "${name} (${url}) の起動を待っています"
  until curl -fsS -o /dev/null --max-time 5 "$url"; do
    if [ "$waited" -ge "$HEALTH_TIMEOUT" ]; then
      echo
      error_message "${name} が ${HEALTH_TIMEOUT} 秒たっても応答しません: ${url}"
      "${COMPOSE[@]}" ps
      exit 1
    fi
    echo -n "."
    sleep 3
    waited=$((waited + 3))
  done
  echo
  success_message "${name}: OK"
}

step_message "API / TOOL が利用できるか確認します"
wait_until_healthy "API" "$API_URL"
wait_until_healthy "TOOL" "$TOOL_URL"

trap - ERR
success_message "デプロイが完了しました。"
