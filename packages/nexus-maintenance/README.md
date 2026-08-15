# Laravel Maintenance

Maintenance mode management for Laravel with support for AWS DynamoDB and Alibaba Cloud TableStore.

## Features

- ✅ Centralized maintenance mode management
- ✅ Time-based maintenance scheduling
- ✅ Support for AWS DynamoDB
- ✅ Support for Alibaba Cloud TableStore  
- ✅ Local cache for performance
- ✅ IP whitelisting for admin access
- ✅ Simple API for maintenance control

## Installation

```bash
composer require laravel-mobile-rpg/maintenance
```

### For AWS DynamoDB

```bash
composer require aws/aws-sdk-php
```

### For Alibaba Cloud TableStore

```bash
composer require aliyun/aliyun-tablestore-sdk-php
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --provider="LaravelMaintenance\MaintenanceServiceProvider" --tag="config"
```

### Environment Variables

```env
# Maintenance Configuration
MAINTENANCE_ENABLED=true
MAINTENANCE_DRIVER=dynamodb  # dynamodb or tablestore
MAINTENANCE_CACHE_TTL=60
MAINTENANCE_EXCLUDED_IPS=192.168.1.1,10.0.0.1

# AWS DynamoDB (when MAINTENANCE_DRIVER=dynamodb)
AWS_DYNAMODB_ENDPOINT=  # For local development only
AWS_DYNAMODB_MAINTENANCE_TABLE=sys_maintenance
AWS_DYNAMODB_MAINTENANCE_PRIMARY_KEY=SysMaintenance
AWS_ACCESS_KEY_ID=your_key
AWS_SECRET_ACCESS_KEY=your_secret
AWS_DEFAULT_REGION=ap-northeast-1

# Alibaba Cloud TableStore (when MAINTENANCE_DRIVER=tablestore)
ALIBABA_TABLESTORE_ENDPOINT=https://instance.region.ots.aliyuncs.com
ALIBABA_TABLESTORE_INSTANCE=your_instance
ALIBABA_TABLESTORE_MAINTENANCE_TABLE=sys_maintenance
ALIBABA_TABLESTORE_MAINTENANCE_PRIMARY_KEY=SysMaintenance
ALIBABA_TABLESTORE_ACCESS_KEY_ID=your_key
ALIBABA_TABLESTORE_ACCESS_KEY_SECRET=your_secret
```

## Database Table Structure

### DynamoDB Table

Table name: `sys_maintenance`

- Primary key: `id` (String) - Value: `SysMaintenance`
- Attributes:
  - `is_maintenance` (Boolean)
  - `start_at` (String - ISO8601)
  - `end_at` (String - ISO8601)
  - `title` (String)
  - `message` (String)
  - `updated_at` (String - ISO8601)

### TableStore Table

Table name: `sys_maintenance`

- Primary key: `id` (String) - Value: `SysMaintenance`
- Columns: Same as DynamoDB

## Usage

### Checking Maintenance Status

```php
use LaravelMaintenance\Services\MaintenanceService;

$maintenanceService = app(MaintenanceService::class);

if ($maintenanceService->isUnderMaintenance()) {
    // System is under maintenance
}
```

### Starting Maintenance

```php
use LaravelMaintenance\DataTransferObjects\MaintenanceInfo;
use LaravelMaintenance\Services\MaintenanceService;
use Carbon\CarbonImmutable;

$service = app(MaintenanceService::class);

$info = new MaintenanceInfo(
    isMaintenance: true,
    startAt: CarbonImmutable::now(),
    endAt: CarbonImmutable::now()->addHours(2),
    title: 'Scheduled Maintenance',
    message: 'We are performing scheduled maintenance.',
    updatedAt: CarbonImmutable::now()
);

$service->startMaintenance($info);
```

### Ending Maintenance

```php
$service = app(MaintenanceService::class);
$service->endMaintenance();
```

### Using Middleware

```php
// In your routes/api.php
Route::middleware(['maintenance'])->group(function () {
    // Routes that should check maintenance status
});
```

## License

MIT
