<?php

namespace NexusVersion;

use Illuminate\Support\ServiceProvider;

class NexusVersionServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Load migrations from specific subdirectories
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations/sys');
    }

    public function register(): void
    {
        //
    }
}
