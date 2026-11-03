#!/usr/bin/env bash
# PostToolUse フック: Edit / Write で書き換えた PHP ファイルを Pint で整形する。
#
# 「make check → pint で落ちる → 直す → もう一度回す」の往復を無くすため。
# 整形の成否で作業を止めない。コンテナが動いていない、jq が無いなどの時は何もせず終わる。

set -u

command -v jq > /dev/null 2>&1 || exit 0

file_path=$(jq -r '.tool_input.file_path // empty' 2> /dev/null)
[ -n "$file_path" ] || exit 0

case "$file_path" in
    *.php) ;;
    *) exit 0 ;;
esac

project_dir="${CLAUDE_PROJECT_DIR:-$(cd "$(dirname "$0")/../.." && pwd)}"
relative_path="${file_path#"$project_dir"/}"

# ホストのパスを、コンテナ内のパスと使うコンテナに読み替える（docker-compose.yml のマウントに合わせる）
case "$relative_path" in
    api/*) service=api-php; container_path="/var/www/html/${relative_path#api/}" ;;
    packages/*) service=api-php; container_path="/var/www/${relative_path}" ;;
    tool/*) service=tool-php; container_path="/var/www/html/${relative_path#tool/}" ;;
    *) exit 0 ;;
esac

cd "$project_dir" || exit 0

# 起動していなければ整形しない（ここで make up はしない）
[ -n "$(docker compose ps -q --status running "$service" 2> /dev/null)" ] || exit 0

docker compose exec -T "$service" ./vendor/bin/pint --quiet "$container_path" > /dev/null 2>&1

exit 0
