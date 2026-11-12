.PHONY: help packages-standalone up down ps logs test test-fresh test-unit test-feature test-quiet coverage coverage-html phpstan check check-quiet migrate migrate-fresh seed seed-recording seed-recording-one seed-recording-reset seed-recording-clear-cache shell tinker clean install pint pint-dirty pint-test tool-check tool-test-db audit

# Compose v2 (docker compose) を優先し、無ければ v1 (docker-compose) にフォールバック
DOCKER_COMPOSE := $(shell docker compose version > /dev/null 2>&1 && echo "docker compose" || echo "docker-compose")

# マイグレーションは api/database/migrations/{group} と
# packages/*/database/migrations/{group} に分かれて置かれている。
# パスを列挙するとパッケージ追加のたびに腐るため、コンテナ内でglobする。
# --path は base_path() からの相対パスとして解決されるため ../packages/... で渡す。
# $(1)=artisanコマンド $(2)=接続名 $(3)=マイグレーショングループ
define migrate_group
	$(DOCKER_COMPOSE) exec -T api-php sh -c 'args=""; for p in database/migrations/$(3) ../packages/*/database/migrations/$(3); do [ -d "$$p" ] && args="$$args --path=$$p"; done; php artisan $(1) --database=$(2) --force $$args'
endef

# *-quiet はエージェント向け。全文は QUIET_LOG_DIR に書き、画面には結果だけを出す。
# 全テストの出力は2700行を超え、そのまま読むとトークンを大きく使う
QUIET_LOG_DIR := .make-logs

# 成功なら1行、失敗ならログの末尾を出す
# $(1)=ログ名 $(2)=コマンド $(3)=失敗時に出す末尾の行数（省略時80）
define run_quiet
	@mkdir -p $(QUIET_LOG_DIR)
	@if $(2) > $(QUIET_LOG_DIR)/$(1).log 2>&1; then echo "OK $(1)"; \
	else tail -n $(or $(3),80) $(QUIET_LOG_DIR)/$(1).log; echo "NG $(1)（全文: $(QUIET_LOG_DIR)/$(1).log）"; exit 1; fi
endef

help: ## ヘルプを表示
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

up: ## Docker環境を起動（Dockerfileの変更があればイメージも作り直す）
	# --build を付けないと、Dockerfileに拡張を足してもイメージが古いままになる。
	# 変更が無ければキャッシュが効くので、毎回付けても待ち時間はほぼ増えない。
	$(DOCKER_COMPOSE) up -d --build

down: ## Docker環境を停止
	$(DOCKER_COMPOSE) down

ps: ## コンテナ一覧を表示
	$(DOCKER_COMPOSE) ps

logs: ## ログを表示
	$(DOCKER_COMPOSE) logs -f api-php

test: up ## テストを実行（Docker環境）
	$(DOCKER_COMPOSE) exec -T api-php php artisan config:clear
	$(DOCKER_COMPOSE) exec -T api-php php artisan test

test-fresh: up migrate-fresh seed ## マイグレーション実行後にテストを実行
	$(DOCKER_COMPOSE) exec -T api-php php artisan config:clear
	$(DOCKER_COMPOSE) exec -T api-php php artisan test

test-unit: up ## ユニットテストのみ実行（Docker環境）
	$(DOCKER_COMPOSE) exec -T api-php php artisan config:clear
	$(DOCKER_COMPOSE) exec -T api-php php artisan test --testsuite=Unit

test-feature: up ## 統合テストのみ実行（Docker環境）
	$(DOCKER_COMPOSE) exec -T api-php php artisan config:clear
	$(DOCKER_COMPOSE) exec -T api-php php artisan test --testsuite=Feature

# 失敗したときは、最初の失敗の区切り線（────）から末尾の集計までを出す。
# 区切り線が無い失敗（致命的エラーなど）はログの末尾を出す
test-quiet: ## 全テスト。全文はログに書き、失敗と集計だけを表示（エージェント向け）
	$(call run_quiet,up,$(DOCKER_COMPOSE) up -d --build,5)
	$(call run_quiet,config-clear,$(DOCKER_COMPOSE) exec -T api-php php artisan config:clear)
	@mkdir -p $(QUIET_LOG_DIR)
	@$(DOCKER_COMPOSE) exec -T api-php php artisan test > $(QUIET_LOG_DIR)/test.log 2>&1; status=$$?; \
	if [ $$status -eq 0 ]; then grep -E '^ *(Tests|Duration):' $(QUIET_LOG_DIR)/test.log; \
	elif grep -q '────' $(QUIET_LOG_DIR)/test.log; then awk '/────/{f=1} f' $(QUIET_LOG_DIR)/test.log | head -n 300; \
	else tail -n 80 $(QUIET_LOG_DIR)/test.log; fi; \
	echo "全文: $(QUIET_LOG_DIR)/test.log"; exit $$status

coverage: up ## カバレッジを計測してテキストで表示（Docker環境）
	$(DOCKER_COMPOSE) exec -T api-php php artisan config:clear
	$(DOCKER_COMPOSE) exec -T api-php ./vendor/bin/phpunit --coverage-text

coverage-html: up ## カバレッジをHTMLで出力（api/storage/coverage/index.html）
	$(DOCKER_COMPOSE) exec -T api-php php artisan config:clear
	$(DOCKER_COMPOSE) exec -T api-php ./vendor/bin/phpunit --coverage-html storage/coverage

phpstan: up ## PHPStan静的解析を実行（Docker環境）
	$(DOCKER_COMPOSE) exec -T api-php ./vendor/bin/phpstan analyse --memory-limit=2G

# パッケージを1つずつ単体で入れて、全クラスが読み込めるか確かめる（composer.json の宣言漏れの検出）。
# 引数でパッケージを絞れる: make packages-standalone PKG="nexus-vip nexus-mission"
packages-standalone: up ## パッケージ単体の検査（宣言漏れ）
	$(DOCKER_COMPOSE) exec -T -e NEXUS_ROOT=/var/www api-php bash -s -- $(PKG) < scripts/check-packages-standalone.sh

# CIと同じ検査をローカルで回す。
# 一部だけを見ていると、ローカルで通してもCIで落ちる
check: audit pint-test phpstan packages-standalone test tool-check ## CIと同じ検査（脆弱性・フォーマット・静的解析・全テスト）

# check と同じ検査を、結果だけ表示で回す
check-quiet: ## checkと同じ検査。結果だけを表示（エージェント向け）
	$(call run_quiet,up,$(DOCKER_COMPOSE) up -d --build,5)
	$(call run_quiet,audit,$(MAKE) --no-print-directory audit)
	$(call run_quiet,pint-test,$(DOCKER_COMPOSE) exec -T api-php sh -c './vendor/bin/pint --test && ./vendor/bin/pint --test ../packages')
	$(call run_quiet,phpstan,$(DOCKER_COMPOSE) exec -T api-php ./vendor/bin/phpstan analyse --memory-limit=2G --no-progress)
	$(call run_quiet,packages-standalone,$(MAKE) --no-print-directory packages-standalone)
	@$(MAKE) --no-print-directory test-quiet
	$(call run_quiet,tool-check,$(MAKE) --no-print-directory tool-check)

# 依存パッケージの既知の脆弱性を検査する（api と tool は composer の依存が別）
audit: up ## 依存パッケージの脆弱性を検査（CIと同じ）
	$(DOCKER_COMPOSE) exec -T api-php composer audit --no-interaction
	$(DOCKER_COMPOSE) exec -T tool-php composer audit --no-interaction

# tool は api とは別のLaravelアプリで、composerの依存も分かれている。
# api-php コンテナからは見えないため、tool-php で回す
tool-check: up tool-test-db ## toolの検査（フォーマット・静的解析・テスト）
	$(DOCKER_COMPOSE) exec -T tool-php ./vendor/bin/pint --test
	$(DOCKER_COMPOSE) exec -T tool-php ./vendor/bin/phpstan analyse --memory-limit=1G --no-progress
	$(DOCKER_COMPOSE) exec -T tool-php ./vendor/bin/phpunit

# tool のテスト用データベースを作る（tool/phpunit.xml が参照する）。
# docker-compose.yml の DB_NAMES はボリュームの初回作成時しか効かないため、既存の環境ではここで作る
tool-test-db: up
	@for target in db-admin:nexus-testing-admin db-system:nexus-testing-tool-system db-shard1:nexus-testing-tool-shard db-shard2:nexus-testing-tool-shard; do \
		$(DOCKER_COMPOSE) exec -T $${target%%:*} sh -c "MYSQL_PWD=\"\$$MYSQL_ROOT_PASSWORD\" mysql -uroot -e 'CREATE DATABASE IF NOT EXISTS \`$${target#*:}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'" || exit 1; \
	done

migrate: up ## マイグレーションを実行
	$(call migrate_group,migrate,sys,sys)
	$(call migrate_group,migrate,mst,mst)
	$(DOCKER_COMPOSE) exec -T api-php php artisan trx:migrate --force
	$(DOCKER_COMPOSE) exec -T api-php php artisan pitr:migrate --force

migrate-fresh: up ## マイグレーションをリセットして再実行（trx/logも作り直す）
	$(call migrate_group,migrate:fresh,sys,sys)
	$(call migrate_group,migrate:fresh,mst,mst)
	$(DOCKER_COMPOSE) exec -T api-php php artisan trx:migrate --fresh --force
	# trx と log は同じシャードDBに同居している。log まで --fresh にすると
	# 直前に作った trx のテーブルを落とすため、log は空のDBへ通常の migrate で足す
	$(DOCKER_COMPOSE) exec -T api-php php artisan pitr:migrate --force
	@echo "初期データ（シャーディング設定・マスター）も消えたので、続けて make seed を流してください"

seed: up ## シーダーを実行
	$(DOCKER_COMPOSE) exec -T api-php php artisan db:seed --force

# 動画撮影用シード（api/database/seeders/Recording/README.md）。
# 追加専用・冪等で、local / testing 以外では実行を拒否する。前提は make seed（基本のシード）が済んでいること

seed-recording: up ## 動画撮影用の既定シード（規約・ログインボーナス・デプロイ）を追加。前提: make seed 済み
	$(DOCKER_COMPOSE) exec -T api-php php artisan db:seed --class='Database\Seeders\Recording\RecordingSeeder' --force

# SEEDER にはシナリオのクラス名だけを書く（例: make seed-recording-one SEEDER=RecordingMaintenanceOnSeeder）
seed-recording-one: up ## 動画撮影用のシナリオを1つ流す（SEEDER=クラス名）。前提: make seed 済み
	@test -n "$(SEEDER)" || { echo "SEEDER=<クラス名> を指定してください（一覧: api/database/seeders/Recording/README.md）"; exit 1; }
	$(DOCKER_COMPOSE) exec -T api-php php artisan db:seed --class='Database\Seeders\Recording\Scenarios\$(SEEDER)' --force

seed-recording-reset: up ## 動画撮影用のデータ（マーカー付きの行）を削除し、メンテナンス解除・daily_login を有効に戻す
	$(DOCKER_COMPOSE) exec -T api-php php artisan db:seed --class='Database\Seeders\Recording\Scenarios\RecordingResetSeeder' --force

# レート制限のカウンター（sign_up は IP あたり 10 回/時間・device あたり 3 回/時間）も消える。
# 撮影で回数を使い切ったときの回復手段。開発環境専用（本番で流さない）
seed-recording-clear-cache: up ## 【開発専用】キャッシュを全消去（メンテ・デプロイの反映と、レート制限の回数をリセット）
	$(DOCKER_COMPOSE) exec -T api-php php artisan cache:clear

shell: up ## api-phpコンテナにシェルで接続
	$(DOCKER_COMPOSE) exec api-php bash

tinker: up ## Tinkerを起動
	$(DOCKER_COMPOSE) exec api-php php artisan tinker

clean: down ## Docker環境をクリーンアップ（ボリュームも削除）
	$(DOCKER_COMPOSE) down -v
	rm -rf api/vendor api/node_modules

install: ## 初回セットアップ（setup.sh に委譲）
	./command/setup.sh

# 引数なしのpintはカレントディレクトリ（api）しか見ないため、
# packages を明示的に渡す。設定は api/pint.json を共有する
pint: ## Laravel Pintでコードフォーマット（api + packages）
	$(DOCKER_COMPOSE) exec -T api-php ./vendor/bin/pint
	$(DOCKER_COMPOSE) exec -T api-php ./vendor/bin/pint ../packages

pint-dirty: ## 変更されたファイルのみPint実行
	$(DOCKER_COMPOSE) exec -T api-php ./vendor/bin/pint --dirty

pint-test: ## コードフォーマットを検証のみ（CIと同じ。書き換えない）
	$(DOCKER_COMPOSE) exec -T api-php ./vendor/bin/pint --test
	$(DOCKER_COMPOSE) exec -T api-php ./vendor/bin/pint --test ../packages
