#!/usr/bin/env bash
# 個人情報（個人メール・ホーム配下の絶対パス・共同作者表記・トークン・個人の作者情報）が
# コミット・文書・コードに入っていないかを、シェルだけで検査する。一般則の正本は Nexus の
# docs/workflow/private-info.md と scripts/check-private-info.sh。ここはその一般則を自己完結で移したもの。
#
# 使い方
#   bash scripts/check-private-info.sh                 ステージされたファイル + 直近 10 件のコミット + git の user 設定
#   bash scripts/check-private-info.sh --all           追跡ファイル全体 + 直近 10 件のコミット + git の user 設定
#   bash scripts/check-private-info.sh --commits N     見るコミット数を変える（0 で見ない）
#   bash scripts/check-private-info.sh --message FILE  コミットメッセージ 1 件だけ（commit-msg フック用）+ git の user 設定
#   bash scripts/check-private-info.sh --range A..B    A..B のコミットだけを見る（--commits より優先）
#   bash scripts/check-private-info.sh --ci            CI 用。git の user 設定は見ない。--range が無ければコミットも見ない
#                                                      例: --all --ci --range <base>..<head>
#
# 検査の 2 層
#   一般則      : このスクリプトのパターン。リポジトリに置く
#   拒否リスト  : .private-denylist（1 行 1 パターン、大文字小文字を区別しない拡張正規表現）。git に入れない。
#                 リポジトリのルートに無ければ Nexus のルート（親ディレクトリ）のものを使う。CI には無いので警告も出さない
# 出力には、違反の場所（ファイル名・行番号）と規則名だけを出す。一致した語は出さない。
# 行末などに `private-info:allow` と書いた行は、一般則の検査から外す（例: ドキュメント中の例示）。
# 終了コード: 違反があれば 1、なければ 0。
set -uo pipefail

SCRIPT_ROOT=$(cd "$(dirname "$0")/.." && pwd)
REPO=$(git rev-parse --show-toplevel 2>/dev/null) || { echo "git リポジトリの中で実行してください" >&2; exit 2; }
cd "$REPO"

MODE=staged; COMMITS=10; MSGFILE=""; RANGE=""; CI=0
while [ $# -gt 0 ]; do
  case "$1" in
    --all) MODE=all ;;
    --commits) COMMITS=${2:?--commits には件数が要る}; shift ;;
    --message) MODE=message; MSGFILE=${2:?--message にはファイルが要る}; shift ;;
    --range) RANGE=${2:?--range には A..B が要る}; shift ;;
    --ci) CI=1 ;;
    *) echo "不明な引数: $1" >&2; exit 2 ;;
  esac
  shift
done

# ---- 一般則のパターン ----
EMAIL_RE='[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}'
# 例外: 公開用の noreply、example ドメイン、ssh の git@、localhost、Godot の画像名（icon@2x.png など）
EMAIL_OK_RE='^git@|(@users\.noreply\.github\.com|@([A-Za-z0-9-]+\.)*example\.(com|org|net)|@localhost|@[0-9]+x\.[A-Za-z]+)$'
PATH_RE='(^|[^A-Za-z0-9_./-])/(Users|home)/[A-Za-z0-9._-]+'
COAUTH_RE='^[[:space:]>*-]*(Co-Authored-By|Signed-off-by)[[:space:]]*:|Generated with \[?Claude'
TOKEN_RE='(ghp|gho|ghs|ghu)_[A-Za-z0-9]{30,}|github_pat_[A-Za-z0-9_]{30,}|AKIA[0-9A-Z]{16}|sk-[A-Za-z0-9_-]{30,}|BEGIN [A-Z ]*PRIV''ATE KEY'
# 作者・コミッターとして許す形。ID は人ごとに違うので数字だけ決める
NOREPLY_RE='^[0-9]+\+BENieBlack@users\.noreply\.github\.com$'
AUTHOR_NAME='BENieBlack'
BOT_RE='^[0-9]+\+dependabot\[bot\]@users\.noreply\.github\.com$'

VIOL=0
report() { # <場所> <行（空なら省略）> <規則名>
  if [ -n "$2" ]; then echo "違反: $1:$2: $3"; else echo "違反: $1: $3"; fi
  VIOL=$((VIOL + 1))
}

# 拒否リスト（空行と # 行を除いた一時ファイルにする。空パターンは全行に一致してしまうため）
TMPDIR_=$(mktemp -d); trap 'rm -rf "$TMPDIR_"' EXIT
DENY=""
for cand in "$REPO/.private-denylist" "$SCRIPT_ROOT/../.private-denylist"; do
  if [ -f "$cand" ]; then
    grep -vE '^[[:space:]]*(#|$)' "$cand" > "$TMPDIR_/deny" || true
    [ -s "$TMPDIR_/deny" ] && DENY="$TMPDIR_/deny"
    break
  fi
done
if [ -z "$DENY" ] && [ $CI -eq 0 ]; then
  echo "警告: 拒否リスト（.private-denylist）が無い。個人名・会社名を 1 行ずつ書いて作る（git に入れない）" >&2
fi

# scan_file <場所の表示名> <実ファイル> <co-author を見るか 1/0>
# 行番号つきで一般則と拒否リストを当てる。一致した語は出さない。
scan_file() {
  local label=$1 f=$2 n line
  # メール: 行ごとに一致を取り出し、例外に当たらないものが 1 つでもあれば違反
  while IFS= read -r line; do
    n=${line%%:*}
    if grep -noE "$EMAIL_RE" "$f" | grep -E "^$n:" | cut -d: -f2- | grep -viE "$EMAIL_OK_RE" >/dev/null; then
      report "$label" "$n" "個人・会社のメールの疑い"
    fi
  done < <(grep -nE "$EMAIL_RE" "$f" | grep -v 'private-info:allow' | cut -d: -f1)
  while IFS= read -r n; do report "$label" "$n" "ホーム配下の絶対パス"; done \
    < <(grep -nE "$PATH_RE" "$f" | grep -v 'private-info:allow' | cut -d: -f1)
  while IFS= read -r n; do report "$label" "$n" "共同作者・署名行"; done \
    < <(grep -niE "$COAUTH_RE" "$f" | grep -v 'private-info:allow' | cut -d: -f1)
  while IFS= read -r n; do report "$label" "$n" "トークン・秘密鍵の疑い"; done \
    < <(grep -nE "$TOKEN_RE" "$f" | grep -v 'private-info:allow' | cut -d: -f1)
  if [ -n "$DENY" ]; then
    while IFS= read -r n; do report "$label" "$n" "拒否リストに一致"; done \
      < <(grep -niE -f "$DENY" "$f" | cut -d: -f1)
  fi
}

# ---- 1. ファイル ----
case "$MODE" in
  staged)
    while IFS= read -r f; do
      [ -n "$f" ] || continue
      git show ":$f" > "$TMPDIR_/blob" 2>/dev/null || continue
      grep -qI '' "$TMPDIR_/blob" 2>/dev/null || [ ! -s "$TMPDIR_/blob" ] || continue  # バイナリは見ない
      scan_file "$f" "$TMPDIR_/blob"
    done < <(git diff --cached --name-only --diff-filter=ACMR)
    ;;
  all)
    while IFS= read -r f; do
      [ -f "$f" ] || continue
      # 依存のロックファイルは第三者パッケージの作者メールを含む公開メタデータなので見ない
      case "$f" in */composer.lock|composer.lock|*/package-lock.json|package-lock.json|*/bun.lock|bun.lock) continue ;; esac
      grep -qI '' "$f" 2>/dev/null || [ ! -s "$f" ] || continue
      scan_file "$f" "$f"
    done < <(git ls-files)
    ;;
  message)
    # git が足す # 始まりのコメント行は除いてから見る
    grep -v '^#' "$MSGFILE" > "$TMPDIR_/msg" || true
    scan_file "コミットメッセージ" "$TMPDIR_/msg"
    ;;
esac

# ---- 2. 対象コミットのメッセージ・作者・コミッター ----
check_ident() { # <場所> <役割> <名前> <メール>
  local ok=1
  if [ "$3" = "$AUTHOR_NAME" ]; then
    printf '%s' "$4" | grep -qE "$NOREPLY_RE" || ok=0
  elif [ "$2" = コミッター ] && [ "$3" = GitHub ] && [ "$4" = noreply@github.com ]; then :  # private-info:allow（GitHub 自身の公開用アドレス。画面でマージしたときのコミッター）
  elif [ "$3" = 'dependabot[bot]' ] && printf '%s' "$4" | grep -qE "$BOT_RE"; then :
  else ok=0; fi
  [ $ok -eq 1 ] || report "$1" "" "$2の名前・メールが公開用の形でない"
  if [ -n "$DENY" ] && printf '%s\n%s\n' "$3" "$4" | grep -qiE -f "$DENY"; then
    report "$1" "" "$2が拒否リストに一致"
  fi
}
SHAS="$TMPDIR_/shas"; : > "$SHAS"
if [ "$MODE" != message ]; then
  if [ -n "$RANGE" ]; then
    git rev-list "$RANGE" > "$SHAS" || { echo "範囲を解決できない: $RANGE" >&2; exit 2; }
  elif [ $CI -eq 0 ] && [ "$COMMITS" -gt 0 ] && git rev-parse -q --verify HEAD >/dev/null; then
    git log -n "$COMMITS" --format=%H > "$SHAS"
  fi
fi
while IFS= read -r sha; do
  [ -n "$sha" ] || continue
  short=$(git rev-parse --short "$sha")
  IFS='|' read -r an ae cn ce < <(git log -1 --format='%an|%ae|%cn|%ce' "$sha")
  check_ident "コミット $short" 作者 "$an" "$ae"
  check_ident "コミット $short" コミッター "$cn" "$ce"
  # dependabot のメッセージは本文に署名行を持つため、dependabot 自身のコミットだけメッセージ検査から外す
  if [ "$an" = 'dependabot[bot]' ]; then continue; fi
  git log -1 --format=%B "$sha" > "$TMPDIR_/msg"
  scan_file "コミット $short のメッセージ" "$TMPDIR_/msg"
done < "$SHAS"

# ---- 3. （CI では見ない）このマシンの git の user 設定（ローカルとグローバルを解決した値）----
cfg_name=$(git config --get user.name || true)
cfg_mail=$(git config --get user.email || true)
if [ $CI -eq 0 ] && { [ "$cfg_name" != "$AUTHOR_NAME" ] || ! printf '%s' "$cfg_mail" | grep -qE "$NOREPLY_RE"; }; then
  report "git の user 設定" "" "user.name / user.email が公開用の形でない"
  echo "  直し方（このリポジトリで実行。ID は GitHub の noreply メールの数字）:" >&2
  echo "    git config user.name $AUTHOR_NAME" >&2
  echo "    git config user.email \"<ID>+$AUTHOR_NAME@users.noreply.github.com\"" >&2
  echo "  まとめて直すなら Nexus で bash scripts/install-hooks.sh" >&2
fi

if [ $VIOL -gt 0 ]; then
  echo "個人情報の検査: 違反 $VIOL 件。直してから再実行する（Nexus の docs/workflow/private-info.md）" >&2
  exit 1
fi
echo "個人情報の検査: 問題なし"
