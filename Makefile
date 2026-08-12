.PHONY: help up down test test-fresh test-unit test-feature phpstan check migrate migrate-fresh seed

help: ## ヘルプを表示
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

up: ## Docker環境を起動
	docker-compose up -d

down: ## Docker環境を停止
	docker-compose down

ps: ## コンテナ一覧を表示
	docker-compose ps

logs: ## ログを表示
	docker-compose logs -f api-php

test: up ## テストを実行（Docker環境）
	@echo "Waiting for databases to be ready..."
	@sleep 5
	docker-compose exec -T api-php php artisan config:clear
	docker-compose exec -T api-php php artisan test

test-fresh: up migrate-fresh seed ## マイグレーション実行後にテストを実行
	docker-compose exec -T api-php php artisan config:clear
	docker-compose exec -T api-php php artisan test

test-unit: up ## ユニットテストのみ実行（Docker環境）
	docker-compose exec -T api-php php artisan config:clear
	docker-compose exec -T api-php php artisan test --testsuite=Unit

test-feature: up ## 統合テストのみ実行（Docker環境）
	docker-compose exec -T api-php php artisan config:clear
	docker-compose exec -T api-php php artisan test --testsuite=Feature

phpstan: up ## PHPStan静的解析を実行（Docker環境）
	docker-compose exec -T api-php ./vendor/bin/phpstan analyse --memory-limit=2G

check: phpstan test-unit ## 静的解析とユニットテストを実行

migrate: up ## マイグレーションを実行
	docker-compose exec -T api-php php artisan migrate --database=sys --path=database/migrations/sys --path=../packages/nexus-core/database/migrations/sys --path=../packages/nexus-friend/database/migrations/sys --path=../packages/nexus-guild/database/migrations/sys --path=../packages/nexus-maintenance/database/migrations/sys --path=../packages/nexus-player/database/migrations/sys --path=../packages/nexus-version/database/migrations/sys --path=../packages/nexus-vip/database/migrations/sys --force
	docker-compose exec -T api-php php artisan migrate --database=mst --path=database/migrations/mst --path=../packages/nexus-core-billing/database/migrations/mst --path=../packages/nexus-gacha/database/migrations/mst --path=../packages/nexus-login/database/migrations/mst --path=../packages/nexus-mailbox/database/migrations/mst --path=../packages/nexus-player/database/migrations/mst --path=../packages/nexus-resource/database/migrations/mst --path=../packages/nexus-vip/database/migrations/mst --force
	docker-compose exec -T api-php php artisan trx:migrate --force
	docker-compose exec -T api-php php artisan pitr:migrate --force

migrate-fresh: up ## マイグレーションをリセットして再実行
	docker-compose exec -T api-php php artisan migrate:fresh --database=sys --path=database/migrations/sys --path=../packages/nexus-core/database/migrations/sys --path=../packages/nexus-friend/database/migrations/sys --path=../packages/nexus-guild/database/migrations/sys --path=../packages/nexus-maintenance/database/migrations/sys --path=../packages/nexus-player/database/migrations/sys --path=../packages/nexus-version/database/migrations/sys --path=../packages/nexus-vip/database/migrations/sys --force
	docker-compose exec -T api-php php artisan migrate:fresh --database=mst --path=database/migrations/mst --path=../packages/nexus-core-billing/database/migrations/mst --path=../packages/nexus-gacha/database/migrations/mst --path=../packages/nexus-login/database/migrations/mst --path=../packages/nexus-mailbox/database/migrations/mst --path=../packages/nexus-player/database/migrations/mst --path=../packages/nexus-resource/database/migrations/mst --path=../packages/nexus-vip/database/migrations/mst --force
	docker-compose exec -T api-php php artisan migrate:shards --force
	docker-compose exec -T api-php php artisan pitr:migrate --force

seed: up ## シーダーを実行
	docker-compose exec -T api-php php artisan db:seed --database=sys --class=Database\\Seeders\\SysDeploySeeder --force

shell: up ## api-phpコンテナにシェルで接続
	docker-compose exec api-php bash

tinker: up ## Tinkerを起動
	docker-compose exec api-php php artisan tinker

clean: down ## Docker環境をクリーンアップ（ボリュームも削除）
	docker-compose down -v
	rm -rf api/vendor api/node_modules

install: up ## 初回セットアップ（Composer install + npm install + マイグレーション）
	docker-compose exec -T api-php composer install
	docker-compose exec -T api-php npm install
	@make migrate-fresh
	@make seed

pint: ## Laravel Pintでコードフォーマット
	cd api && ./vendor/bin/pint

pint-dirty: ## 変更されたファイルのみPint実行
	cd api && ./vendor/bin/pint --dirty
