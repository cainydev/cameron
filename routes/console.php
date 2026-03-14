<?php

use App\Jobs\EvaluateSingleGoal;
use App\Models\AgentGoal;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Goal Evaluation Dispatcher
|--------------------------------------------------------------------------
|
| Runs hourly. First deactivates any expired temporal goals, then dispatches
| a queued EvaluateSingleGoal job for each remaining active goal. This
| ensures sensor I/O is handled by the queue worker, not the scheduler.
|
*/
Schedule::call(function () {
    AgentGoal::query()
        ->where('is_active', true)
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->update(['is_active' => false]);

    AgentGoal::query()
        ->where('is_active', true)
        ->each(fn (AgentGoal $goal) => EvaluateSingleGoal::dispatch($goal));
})->hourly()->name('goal-evaluation-dispatcher')->withoutOverlapping();
