<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('poso:backup')->dailyAt('23:00')->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Abandoned previews may leave private uploads; confirmed attachments are retained.
Artisan::command('poso:prune-minor-photos', function () {
    $disk = \Illuminate\Support\Facades\Storage::disk('local');
    foreach ($disk->files('minor-photos') as $path) {
        if ($disk->lastModified($path) < now()->subDay()->timestamp
            && !\App\Models\Violation::withTrashed()->whereJsonContains('minor_photos', $path)->exists()) {
            $disk->delete($path);
        }
    }
})->purpose('Remove abandoned minor photo uploads older than one day');

Schedule::command('poso:prune-minor-photos')->daily()->withoutOverlapping();
