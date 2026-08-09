#!/bin/bash

set -e

echo "Setting up test databases..."

# Drop and recreate all test databases
docker-compose exec -T db-sys mysql -uroot -proot -e "DROP DATABASE IF EXISTS \`nexus-testing-sys\`; CREATE DATABASE \`nexus-testing-sys\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>&1 | grep -v "Using a password"
docker-compose exec -T db-mst mysql -uroot -proot -e "DROP DATABASE IF EXISTS \`nexus-testing-mst\`; CREATE DATABASE \`nexus-testing-mst\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>&1 | grep -v "Using a password"

for shard in 1 2 3; do
    docker-compose exec -T db-trx$shard mysql -uroot -proot -e "DROP DATABASE IF EXISTS \`nexus-testing-trx$shard\`; CREATE DATABASE \`nexus-testing-trx$shard\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>&1 | grep -v "Using a password"
    docker-compose exec -T db-log$shard mysql -uroot -proot -e "DROP DATABASE IF EXISTS \`nexus-testing-log$shard\`; CREATE DATABASE \`nexus-testing-log$shard\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>&1 | grep -v "Using a password"
done

echo "Running migrations..."
docker-compose exec -T -e APP_ENV=testing -e DB_CONNECTION=sys api-php php artisan migrate --force

echo "Running seeders..."
docker-compose exec -T -e APP_ENV=testing api-php php artisan db:seed --database=sys --class=Database\\Seeders\\SysDeploySeeder --force

echo "Creating migration flag..."
docker-compose exec -T api-php mkdir -p /var/www/html/storage/framework/testing
docker-compose exec -T api-php touch /var/www/html/storage/framework/testing/.migrated

echo "Test databases are ready!"
