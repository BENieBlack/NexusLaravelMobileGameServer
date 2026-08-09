<?php

use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    // MaintenanceServiceProviderはパッケージから自動検出されます
    \NexusVersion\NexusVersionServiceProvider::class,
];
