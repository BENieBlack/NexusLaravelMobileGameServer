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

test-unit: ## ユニットテストのみ実行（DB不要、ローカル実行）
	cd api && php artisan config:clear
	cd api && php artisan test --filter="ErrorResponse|BaseModelDateCast|MaintenanceDto|CheckMaintenance|BaseController|ExampleTest"

test-feature: up ## 統合テストのみ実行（Docker環境）
	docker-compose exec -T api-php php artisan config:clear
	docker-compose exec -T api-php php artisan test --testsuite=Feature

phpstan: ## PHPStan静的解析を実行
	cd api && ./vendor/bin/phpstan analyse --memory-limit=2G

check: phpstan test-unit ## 静的解析とユニットテストを実行

migrate: up ## マイグレーションを実行
	docker-compose exec -T api-php php artisan migrate --database=sys --path=database/migrations/sys --force
	docker-compose exec -T api-php php artisan migrate --database=mst --path=database/migrations/mst --force
	docker-compose exec -T api-php php artisan migrate --database=trx --path=database/migrations/trx --force
	docker-compose exec -T api-php php artisan migrate --database=log --path=database/migrations/log --force

migrate-fresh: up ## マイグレーションをリセットして再実行
	docker-compose exec -T api-php php artisan migrate:fresh --database=sys --path=database/migrations/sys --force
	docker-compose exec -T api-php php artisan migrate:fresh --database=mst --path=database/migrations/mst --force
	docker-compose exec -T api-php php artisan migrate:fresh --database=trx --path=database/migrations/trx --force
	docker-compose exec -T api-php php artisan migrate:fresh --database=log --path=database/migrations/log --force

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
