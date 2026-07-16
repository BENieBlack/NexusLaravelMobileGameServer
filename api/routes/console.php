<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 期限切れメール自動削除（毎日午前3時）
Schedule::command('mailbox:delete-expired')->dailyAt('03:00');
