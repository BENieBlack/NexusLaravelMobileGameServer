#!/usr/bin/env bash
# パッケージを1つずつ単体で入れて、全クラスが読み込めるか確かめる。
#
# 目的: パッケージの composer.json に、使っているライブラリ（Illuminate、JWT など）の宣言漏れが無いかを調べる。
# api/ の vendor には全部入っているので、api で動いても、単体で入れると動かないことがある。
#
# やること: packages/ の各パッケージについて、空のプロジェクトに「そのパッケージだけ」を composer で入れ、
# src の全クラス（クラス・インターフェース・トレイト・enum）を autoload で読む。
# 読み込めれば、親クラス・interface・trait の宣言漏れは無い。メソッド本体の中だけで使うクラスは見ない
# （それは PackageLayerTest が、他パッケージへの依存について検査している）。
#
# 実行: make packages-standalone（Docker 上で動かす。CI ではそのまま bash で動かす）
set -uo pipefail

ROOT="${NEXUS_ROOT:-$(cd "$(dirname "$0")/.." && pwd)}"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

if [ "$#" -gt 0 ]; then
  PACKAGES=("$@")
else
  PACKAGES=()
  for dir in "$ROOT"/packages/*/; do PACKAGES+=("$(basename "$dir")"); done
fi

cat >"$WORK/load.php" <<'PHP'
<?php
// 使い方: php load.php <パッケージのsrcの絶対パス> <vendor/autoload.php>
[$script, $src, $autoload] = $argv;
require $autoload;

$prefix = null;
$composer = json_decode(file_get_contents(dirname($src).'/composer.json'), true);
foreach ($composer['autoload']['psr-4'] ?? [] as $ns => $path) {
    if (rtrim($path, '/') === 'src') {
        $prefix = $ns;
    }
}
if ($prefix === null) {
    fwrite(STDERR, "psr-4 の src が見つかりません\n");
    exit(1);
}

$failed = 0;
$count = 0;
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $relative = substr($file->getPathname(), strlen($src) + 1, -4);
    $class = $prefix.str_replace('/', '\\', $relative);

    // クラス名に対応しないファイル（設定ファイルなど）は、読まない
    if (! preg_match('/^\s*(?:final\s+|abstract\s+|readonly\s+)*(?:class|interface|trait|enum)\s+/m', file_get_contents($file->getPathname()))) {
        continue;
    }

    $count++;
    try {
        if (! class_exists($class) && ! interface_exists($class) && ! trait_exists($class) && ! enum_exists($class)) {
            echo "  読み込めない: {$class}\n";
            $failed++;
        }
    } catch (Throwable $e) {
        echo "  {$class}: ".$e->getMessage()."\n";
        $failed++;
    }
}

echo "  {$count} クラス確認\n";
exit($failed === 0 ? 0 : 1);
PHP

status=0
for name in "${PACKAGES[@]}"; do
  dir="$ROOT/packages/$name"
  [ -f "$dir/composer.json" ] || { echo "SKIP $name (composer.json なし)"; continue; }
  composer_name="$(php -r 'echo json_decode(file_get_contents($argv[1]), true)["name"];' "$dir/composer.json")"

  project="$WORK/$name"
  mkdir -p "$project"
  cat >"$project/composer.json" <<JSON
{
    "name": "check/$name",
    "repositories": [
        {"type": "path", "url": "$ROOT/packages/*", "options": {"symlink": false}}
    ],
    "require": {"$composer_name": "@dev"},
    "minimum-stability": "dev",
    "prefer-stable": true,
    "config": {"allow-plugins": false}
}
JSON

  echo "== $name ($composer_name)"
  if ! (cd "$project" && composer install --no-scripts --no-interaction --no-progress --quiet 2>&1 | sed 's/^/  /'); then
    echo "NG  $name: composer install に失敗"
    status=1
    continue
  fi

  # 入れたパッケージの src は、コピーされた vendor 側を見る
  src="$project/vendor/$composer_name/src"
  if ! php "$WORK/load.php" "$src" "$project/vendor/autoload.php"; then
    echo "NG  $name"
    status=1
  else
    echo "OK  $name"
  fi
done

exit "$status"
