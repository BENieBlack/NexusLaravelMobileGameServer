.PHONY: help up down ps logs test test-fresh test-unit test-feature coverage coverage-html phpstan check migrate migrate-fresh seed shell tinker clean install pint pint-dirty pint-test

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

coverage: up ## カバレッジを計測してテキストで表示（Docker環境）
	$(DOCKER_COMPOSE) exec -T api-php php artisan config:clear
	$(DOCKER_COMPOSE) exec -T api-php ./vendor/bin/phpunit --coverage-text

coverage-html: up ## カバレッジをHTMLで出力（api/storage/coverage/index.html）
	$(DOCKER_COMPOSE) exec -T api-php php artisan config:clear
	$(DOCKER_COMPOSE) exec -T api-php ./vendor/bin/phpunit --coverage-html storage/coverage

phpstan: up ## PHPStan静的解析を実行（Docker環境）
	$(DOCKER_COMPOSE) exec -T api-php ./vendor/bin/phpstan analyse --memory-limit=2G

# CIと同じ検査をローカルで回す。
# 一部だけを見ていると、ローカルで通してもCIで落ちる
check: pint-test phpstan test ## CIと同じ検査（フォーマット・静的解析・全テスト）

migrate: up ## マイグレーションを実行
	$(call migrate_group,migrate,sys,sys)
	$(call migrate_group,migrate,mst,mst)
	$(DOCKER_COMPOSE) exec -T api-php php artisan trx:migrate --force
	$(DOCKER_COMPOSE) exec -T api-php php artisan pitr:migrate --force

migrate-fresh: up ## マイグレーションをリセットして再実行（trx/logも作り直す）
	$(call migrate_group,migrate:fresh,sys,sys)
	$(call migrate_group,migrate:fresh,mst,mst)
	$(DOCKER_COMPOSE) exec -T api-php php artisan trx:migrate --fresh --force
	$(DOCKER_COMPOSE) exec -T api-php php artisan pitr:migrate --fresh --force

seed: up ## シーダーを実行
	$(DOCKER_COMPOSE) exec -T api-php php artisan db:seed --force

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
