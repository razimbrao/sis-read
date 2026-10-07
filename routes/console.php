<?php

use Illuminate\Support\Facades\Schedule;

// Hospedagens só com cron: o agendador mantém um worker vivo. No Docker (docs/deploy.md) o
// worker já roda sob o supervisord, e este agendamento criaria um segundo worker.
if (! config('queue.worker_supervisionado')) {
    Schedule::command('queue:work --timeout=600')->everyMinute()->withoutOverlapping();
}

// Cópia diária do SQLite para o volume (ver docker/backup-sqlite.sh).
Schedule::exec('sh '.base_path('docker/backup-sqlite.sh'))
    ->dailyAt('06:00')
    ->when(fn () => config('database.default') === 'sqlite' && is_file('/data/database.sqlite'));
