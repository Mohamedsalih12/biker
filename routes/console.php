<?php

use App\Console\Commands\ExpireUnpaidBookings;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Spatie\DeletedModels\Models\DeletedModel;
use Spatie\OneTimePasswords\Models\OneTimePassword;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(ExpireUnpaidBookings::class)->everyMinute()->withoutOverlapping();

Schedule::command('model:prune', [
    '--model' => [
        OneTimePassword::class,
        DeletedModel::class,
    ],
])->daily();

Schedule::command('backup:run')->dailyAt('4:00')->appendOutputTo(storage_path('logs/backup.log'));
// Schedule::command("backup:run")->everyMinute()->appendOutputTo(storage_path('logs/backup.log'));
