#!/bin/bash
#
# CS対応スキルが使うラベルを作成する。
#
# 何度実行しても安全（既にあるラベルは黙って飛ばす）。
# 初回のCS対応時に一度だけ実行すればよい。
#
# Usage:
#   bash .claude/skills/cs-support/scripts/ensure-labels.sh [owner/repo]

set -euo pipefail

REPO="${1:-BENieBlack/NexusLaravelMobileGameServer}"

# "ラベル名|色|説明"
LABELS=(
  "cs|D93F0B|CS問い合わせ起因の調査記録"
  "cs:login-bonus|FBCA04|ログインボーナス（通常/VIP/カムバック）"
  "cs:billing|0E8A16|課金・レシート・通貨残高"
  "cs:gacha|1D76DB|ガチャ抽選・排出・天井"
  "cs:item-loss|B60205|アイテム/ユニット/装備の欠損"
  "cs:mailbox|5319E7|メールボックス・受取期限"
  "cs:auth|006B75|ログイン・認証・データ引き継ぎ"
  "cs:stamina|C2E0C6|スタミナ回復"
  "cs:guild|BFD4F2|ギルド・申請"
  "cs:friend|D4C5F9|フレンド"
  "cs:other|CCCCCC|上記に当てはまらないもの"
)

if ! command -v gh > /dev/null 2>&1; then
  echo "gh コマンドが見つかりません。GitHub CLI をインストールしてください。" >&2
  exit 1
fi

echo "対象リポジトリ: ${REPO}"

created=0
skipped=0
for entry in "${LABELS[@]}"; do
  IFS='|' read -r name color description <<< "$entry"
  if gh label create "$name" \
      --repo "$REPO" \
      --color "$color" \
      --description "$description" > /dev/null 2>&1; then
    echo "  作成: ${name}"
    created=$((created + 1))
  else
    # 既存の場合はここに来る。説明や色のズレは直さない（手動で変えている可能性があるため）
    echo "  既存: ${name}"
    skipped=$((skipped + 1))
  fi
done

echo "完了: 作成 ${created} 件 / 既存 ${skipped} 件"
