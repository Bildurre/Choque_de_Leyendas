<?php

use Illuminate\Support\Facades\Route;

/*
| Rutas propias del juego. Se cargan con prefijo /api y el grupo de middleware
| 'api'. Las rutas del motor (auth, contenido, PDF, configuración, usuarios,
| copias…) las añade el propio paquete edc-motor/core.
|
| Patrón para cada entidad (ver guia-como-montar-una-web.md §7):
|
| // Público (solo entidades publicadas)
| Route::get('cartas', [PublicCartaController::class, 'index']);
| Route::get('cartas/{slug}', [PublicCartaController::class, 'show']);
|
| // Admin (exige manage-game)
| Route::middleware(['auth:sanctum', 'motor.admin', 'can:manage-game'])
|     ->prefix('admin')->group(function () {
|         Route::get('cartas', [CartaController::class, 'index']);
|         // store/show/update/destroy/restore/force/toggle-published…
|     });
*/
