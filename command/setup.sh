#!/bin/bash
#
# Environment Setup Script for LaravelMobileGameExpantion
#
# This script performs a complete clean installation of the development environment.
# 
# WARNING: This script will DELETE all existing Docker volumes and data!
#          Only use this for initial setup or when you need a complete reset.
#
# What this script does:
#   1. Remove all existing Docker containers and volumes
#   2. Start all Docker containers (web servers, PHP, databases, Redis)
#   3. Create all 7 databases (sys, mst, log, trx1, trx2, admin, tool)
#   4. Install dependencies (Composer, NPM) for both API and Tool projects
#   5. Run database migrations for all databases
#
# Usage:
#   ./command/setup.sh
#
# Requirements:
#   - Docker and Docker Compose must be installed
#   - .env file must exist in the project root
#   - APP_NAME and APP_ENV must be set in .env file
#

# Exit immediately if a command exits with a non-zero status
set -e

# Get the directory where this script is located
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# Project root is one level up from the script directory
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

# Define colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Function to print success message
function success_message() {
  echo -e "${GREEN}$1${NC}"
}

# Function to print error message
function error_message() {
  echo -e "${RED}$1${NC}"
}

# Function to read .env file and extract specific variable
function get_env_value() {
  local env_file=$1
  local key=$2
  if [ -f "$env_file" ]; then
    grep "^${key}=" "$env_file" | cut -d '=' -f2- | tr -d '"' | tr -d "'"
  else
    echo ""
  fi
}

# Get APP_NAME and APP_ENV from root .env file
ROOT_ENV_FILE="${PROJECT_ROOT}/.env"
if [ ! -f "$ROOT_ENV_FILE" ]; then
  error_message "Root .env file not found at $ROOT_ENV_FILE"
  exit 1
fi

APP_NAME=$(get_env_value "$ROOT_ENV_FILE" "APP_NAME")
APP_ENV=$(get_env_value "$ROOT_ENV_FILE" "APP_ENV")

if [ -z "$APP_NAME" ] || [ -z "$APP_ENV" ]; then
  error_message "APP_NAME or APP_ENV not found in $ROOT_ENV_FILE"
  exit 1
fi

echo "Using APP_NAME: ${APP_NAME}"
echo "Using APP_ENV: ${APP_ENV}"

# Change to project root directory for docker-compose
cd "$PROJECT_ROOT"

# ============================================================================
# Step 1: Set up Docker containers
# ============================================================================
echo "Setting up Docker containers..."
echo "WARNING: This will remove all existing containers and volumes!"
docker-compose down --volumes --remove-orphans
success_message "Removed existing Docker containers."
docker-compose up -d
success_message "Docker containers are up and running."

# Wait for MySQL containers to be ready
echo "Waiting for MySQL containers to be ready..."
sleep 10

# ============================================================================
# Step 2: Define database names
# ============================================================================
# Define environment variables explicitly
MYSQL_ROOT_PASSWORD="root"
# Database naming format: {APP_NAME}-{APP_ENV}-{prefix}
# Example: arche-local-sys, arche-local-mst, etc.
DB_SYS_DATABASE="${APP_NAME}-${APP_ENV}-sys"
DB_MASTER_DATABASE="${APP_NAME}-${APP_ENV}-mst"
DB_LOG_DATABASE="${APP_NAME}-${APP_ENV}-log"
DB_TRX1_DATABASE="${APP_NAME}-${APP_ENV}-trx1"
DB_TRX2_DATABASE="${APP_NAME}-${APP_ENV}-trx2"
DB_ADMIN_DATABASE="${APP_NAME}-${APP_ENV}-adm"
DB_TOOL_DATABASE="${APP_NAME}-${APP_ENV}-tol"

echo "Database names:"
echo "  System: ${DB_SYS_DATABASE}"
echo "  Master: ${DB_MASTER_DATABASE}"
echo "  Log: ${DB_LOG_DATABASE}"
echo "  Transaction 1: ${DB_TRX1_DATABASE}"
echo "  Transaction 2: ${DB_TRX2_DATABASE}"
echo "  Admin: ${DB_ADMIN_DATABASE}"
echo "  Tool: ${DB_TOOL_DATABASE}"

# ============================================================================
# Step 3: Create databases and migrations tables
# ============================================================================
echo "Setting up databases..."

# Create databases if they do not exist
echo "Creating databases..."
docker exec db-sys mysql -u root -p${MYSQL_ROOT_PASSWORD} -e "CREATE DATABASE IF NOT EXISTS \`${DB_SYS_DATABASE}\`;"
docker exec db-mst mysql -u root -p${MYSQL_ROOT_PASSWORD} -e "CREATE DATABASE IF NOT EXISTS \`${DB_MASTER_DATABASE}\`;"
docker exec db-log mysql -u root -p${MYSQL_ROOT_PASSWORD} -e "CREATE DATABASE IF NOT EXISTS \`${DB_LOG_DATABASE}\`;"
docker exec db-trx1 mysql -u root -p${MYSQL_ROOT_PASSWORD} -e "CREATE DATABASE IF NOT EXISTS \`${DB_TRX1_DATABASE}\`;"
docker exec db-trx2 mysql -u root -p${MYSQL_ROOT_PASSWORD} -e "CREATE DATABASE IF NOT EXISTS \`${DB_TRX2_DATABASE}\`;"
docker exec db-adm mysql -u root -p${MYSQL_ROOT_PASSWORD} -e "CREATE DATABASE IF NOT EXISTS \`${DB_ADMIN_DATABASE}\`;"
docker exec db-tol mysql -u root -p${MYSQL_ROOT_PASSWORD} -e "CREATE DATABASE IF NOT EXISTS \`${DB_TOOL_DATABASE}\`;"

# Create migrations table for each database if it doesn't exist
# This ensures Laravel's migration tracking works correctly
echo "Creating migrations tables..."
docker exec db-sys mysql -u root -p${MYSQL_ROOT_PASSWORD} ${DB_SYS_DATABASE} -e "CREATE TABLE IF NOT EXISTS migrations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  migration VARCHAR(255) NOT NULL,
  batch INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"

docker exec db-mst mysql -u root -p${MYSQL_ROOT_PASSWORD} ${DB_MASTER_DATABASE} -e "CREATE TABLE IF NOT EXISTS migrations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  migration VARCHAR(255) NOT NULL,
  batch INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"

docker exec db-log mysql -u root -p${MYSQL_ROOT_PASSWORD} ${DB_LOG_DATABASE} -e "CREATE TABLE IF NOT EXISTS migrations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  migration VARCHAR(255) NOT NULL,
  batch INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"

docker exec db-trx1 mysql -u root -p${MYSQL_ROOT_PASSWORD} ${DB_TRX1_DATABASE} -e "CREATE TABLE IF NOT EXISTS migrations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  migration VARCHAR(255) NOT NULL,
  batch INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"

docker exec db-trx2 mysql -u root -p${MYSQL_ROOT_PASSWORD} ${DB_TRX2_DATABASE} -e "CREATE TABLE IF NOT EXISTS migrations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  migration VARCHAR(255) NOT NULL,
  batch INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"

docker exec db-adm mysql -u root -p${MYSQL_ROOT_PASSWORD} ${DB_ADMIN_DATABASE} -e "CREATE TABLE IF NOT EXISTS migrations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  migration VARCHAR(255) NOT NULL,
  batch INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"

docker exec db-tol mysql -u root -p${MYSQL_ROOT_PASSWORD} ${DB_TOOL_DATABASE} -e "CREATE TABLE IF NOT EXISTS migrations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  migration VARCHAR(255) NOT NULL,
  batch INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"

success_message "Databases and migrations tables created."

# Install dependencies for API
if [ -d "${PROJECT_ROOT}/api" ]; then
  echo "Setting up API..."
  cd "${PROJECT_ROOT}/api"
  composer install
  npm install
  npm run build
  
  # Run migrations for each database
  echo "Running API migrations..."
  docker exec api-php php artisan migrate --path=database/migrations/sys --database=sys --force
  docker exec api-php php artisan migrate --path=database/migrations/mst --database=mst --force
  docker exec api-php php artisan migrate --path=database/migrations/log --database=log --force
  # Note: trx migrations handle both trx1 and trx2 internally via $connections array
  docker exec api-php php artisan migrate --path=database/migrations/trx --database=trx1 --force

  success_message "API setup completed."
else
  error_message "API directory not found."
fi

# Install dependencies for Tool
if [ -d "${PROJECT_ROOT}/tool" ]; then
  echo "Setting up Tool..."
  cd "${PROJECT_ROOT}/tool"
  composer install
  # Remove package-lock.json to avoid dependency conflicts
  rm -f package-lock.json
  npm install
  npm run build
  
  # Run migrations for admin and tool databases
  echo "Running Tool migrations..."
  docker exec tool-php php artisan migrate --path=database/migrations/adm --database=admin --force
  docker exec tool-php php artisan migrate --path=database/migrations/tol --database=tool --force
  
  # Run seeders to create default admin account
  echo "Running Tool seeders..."
  docker exec tool-php php artisan db:seed --class=AdminAccountSeeder --force
  
  success_message "Tool setup completed."
else
  error_message "Tool directory not found."
fi

success_message "Environment setup completed successfully."
