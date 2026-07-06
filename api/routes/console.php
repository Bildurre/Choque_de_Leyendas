<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// La copia de seguridad automática (doc 06) la programa el MOTOR según lo
// configurado en el admin (vista Copias); aquí no hay nada que declarar.

// PDFs temporales caducados (doc 02).
Schedule::command('pdf:cleanup')->hourly();
