<?php

use App\Models\KnowledgeSearchInteraction;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('knowledge:purge-search-interactions', function (): void {
    $deleted = KnowledgeSearchInteraction::query()
        ->where('expires_at', '<=', now())
        ->delete();

    $this->info("Удалено записей журнала: {$deleted}.");
})->purpose('Удалить просроченный журнал AI-поиска');

Schedule::command('knowledge:purge-search-interactions')->dailyAt('02:00');
