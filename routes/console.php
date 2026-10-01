<?php

use App\Jobs\ImportProfile;
use App\Models\SearchProfile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('acquisition:import', function () {
    foreach (SearchProfile::where('active', true)->cursor() as $profile) {
        foreach ($profile->sources as $source) {
            ImportProfile::dispatch($profile->id, $source);
        }
    }
    $this->info('Importjobs eingeplant.');
});
Schedule::command('acquisition:import')->dailyAt('06:00')->timezone('Europe/Berlin')->withoutOverlapping();
