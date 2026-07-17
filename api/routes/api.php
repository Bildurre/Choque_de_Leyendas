<?php

use App\Http\Controllers\AttackRangeController;
use App\Http\Controllers\AttackSubtypeController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\CardSubtypeController;
use App\Http\Controllers\CardTypeController;
use App\Http\Controllers\CounterController;
use App\Http\Controllers\DashboardStatsController;
use App\Http\Controllers\DeckAttributesConfigurationController;
use App\Http\Controllers\EquipmentSubtypeController;
use App\Http\Controllers\EquipmentTypeController;
use App\Http\Controllers\FactionController;
use App\Http\Controllers\FactionDeckController;
use App\Http\Controllers\GameModeController;
use App\Http\Controllers\HeroAbilityController;
use App\Http\Controllers\HeroAttributesConfigurationController;
use App\Http\Controllers\HeroClassController;
use App\Http\Controllers\HeroController;
use App\Http\Controllers\HeroRaceController;
use App\Http\Controllers\HeroSuperclassController;
use App\Http\Controllers\LifeCounterMatchController;
use App\Http\Controllers\Public\PublicCardController;
use App\Http\Controllers\Public\PublicFactionController;
use App\Http\Controllers\Public\PublicFactionDeckController;
use App\Http\Controllers\Public\PublicHeroController;
use App\Models\Card;
use App\Models\Hero;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
| Rutas propias del juego. Se cargan con prefijo /api y el grupo de middleware
| 'api'. Las rutas del motor (auth, contenido, PDF, configuración, usuarios,
| copias…) las añade el propio paquete edc-motor/core.
|
| Convenciones (guia-como-montar-una-web.md §7 + CONVENTIONS.md):
| - Solo rutas de admin en esta fase (nada público).
| - Las rutas literales (options, for-mode) SIEMPRE antes de {slug}/{id}.
| - Entidades con slug traducible: show/update/destroy/toggle por slug;
|   restore/force por id. Taxonomías simples: todo por id.
*/

/*
| Público — web pública (facciones, mazos, índices de cartas y héroes con
| filtros y singles de cartas/héroes). Solo lectura, sin auth, SOLO
| publicado; locale via SetLocale (grupo api). Los índices de cartas y
| héroes extienden al catálogo del motor (/api/catalog/{key}) con los
| filtros de juego. Literales (filters) SIEMPRE antes de {slug}.
*/
Route::get('factions', [PublicFactionController::class, 'index']);
Route::get('factions/{slug}', [PublicFactionController::class, 'show']);
Route::get('faction-decks', [PublicFactionDeckController::class, 'index']);
Route::get('faction-decks/filters', [PublicFactionDeckController::class, 'filters']); // antes de {slug}
Route::get('faction-decks/{slug}', [PublicFactionDeckController::class, 'show']);
Route::get('cards', [PublicCardController::class, 'index']);
Route::get('cards/filters', [PublicCardController::class, 'filters']); // antes de {slug}
Route::get('cards/{slug}', [PublicCardController::class, 'show']);
Route::get('heroes', [PublicHeroController::class, 'index']);
Route::get('heroes/filters', [PublicHeroController::class, 'filters']); // antes de {slug}
Route::get('heroes/{slug}', [PublicHeroController::class, 'show']);

/*
| Contador de vidas (herramienta de la web pública): histórico de partidas
| del usuario autenticado (los invitados juegan solo con localStorage).
| Sin rol de admin: cualquier usuario registrado. El PUT llega debounceado
| (~2 s) desde el cliente al cambiar vidas; throttle por si acaso.
*/
Route::middleware('auth:sanctum')->prefix('life-counter')->group(function () {
    Route::get('matches', [LifeCounterMatchController::class, 'index']);
    Route::post('matches', [LifeCounterMatchController::class, 'store'])
        ->middleware('throttle:30,1');
    Route::put('matches/{id}', [LifeCounterMatchController::class, 'update'])
        ->whereNumber('id')->middleware('throttle:60,1');
    Route::post('matches/{id}/finish', [LifeCounterMatchController::class, 'finish'])
        ->whereNumber('id')->middleware('throttle:30,1');
});

Route::middleware(['auth:sanctum', 'motor.admin', 'can:manage-game'])
    ->prefix('admin')->group(function () {
        // Attack ranges
        Route::get('attack-ranges/options', [AttackRangeController::class, 'options']); // antes de {id}
        Route::get('attack-ranges', [AttackRangeController::class, 'index']);
        Route::post('attack-ranges', [AttackRangeController::class, 'store']);
        Route::put('attack-ranges/{id}', [AttackRangeController::class, 'update']);
        Route::delete('attack-ranges/{id}', [AttackRangeController::class, 'destroy']);
        Route::post('attack-ranges/{id}/restore', [AttackRangeController::class, 'restore']);
        Route::delete('attack-ranges/{id}/force', [AttackRangeController::class, 'forceDestroy']);

        // Attack subtypes
        Route::get('attack-subtypes/options', [AttackSubtypeController::class, 'options']); // antes de {id}
        Route::get('attack-subtypes', [AttackSubtypeController::class, 'index']);
        Route::post('attack-subtypes', [AttackSubtypeController::class, 'store']);
        Route::put('attack-subtypes/{id}', [AttackSubtypeController::class, 'update']);
        Route::delete('attack-subtypes/{id}', [AttackSubtypeController::class, 'destroy']);
        Route::post('attack-subtypes/{id}/restore', [AttackSubtypeController::class, 'restore']);
        Route::delete('attack-subtypes/{id}/force', [AttackSubtypeController::class, 'forceDestroy']);

        // Card subtypes
        Route::get('card-subtypes/options', [CardSubtypeController::class, 'options']); // antes de {id}
        Route::get('card-subtypes', [CardSubtypeController::class, 'index']);
        Route::post('card-subtypes', [CardSubtypeController::class, 'store']);
        Route::get('card-subtypes/{id}', [CardSubtypeController::class, 'show']);
        Route::put('card-subtypes/{id}', [CardSubtypeController::class, 'update']);
        Route::delete('card-subtypes/{id}', [CardSubtypeController::class, 'destroy']);
        Route::post('card-subtypes/{id}/restore', [CardSubtypeController::class, 'restore']);
        Route::delete('card-subtypes/{id}/force', [CardSubtypeController::class, 'forceDestroy']);

        // Card types
        Route::get('card-types/options', [CardTypeController::class, 'options']); // antes de {id}
        Route::get('card-types', [CardTypeController::class, 'index']);
        Route::post('card-types', [CardTypeController::class, 'store']);
        Route::get('card-types/{id}', [CardTypeController::class, 'show']);
        Route::put('card-types/{id}', [CardTypeController::class, 'update']);
        Route::delete('card-types/{id}', [CardTypeController::class, 'destroy']);
        Route::post('card-types/{id}/restore', [CardTypeController::class, 'restore']);
        Route::delete('card-types/{id}/force', [CardTypeController::class, 'forceDestroy']);

        // Cards
        Route::get('cards/options', [CardController::class, 'options']); // antes de {slug}
        Route::get('cards', [CardController::class, 'index']);
        Route::post('cards', [CardController::class, 'store']);
        Route::get('cards/{slug}', [CardController::class, 'show']);
        Route::put('cards/{slug}', [CardController::class, 'update']);
        Route::delete('cards/{slug}', [CardController::class, 'destroy']);
        Route::post('cards/{id}/restore', [CardController::class, 'restore']);
        Route::delete('cards/{id}/force', [CardController::class, 'forceDestroy']);
        Route::post('cards/{slug}/toggle-published', [CardController::class, 'togglePublished']);

        // Counters (por id, sin slug)
        Route::get('counters', [CounterController::class, 'index']);
        Route::post('counters', [CounterController::class, 'store']);
        Route::get('counters/{id}', [CounterController::class, 'show']);
        Route::put('counters/{id}', [CounterController::class, 'update']);
        Route::delete('counters/{id}', [CounterController::class, 'destroy']);
        Route::post('counters/{id}/restore', [CounterController::class, 'restore']);
        Route::delete('counters/{id}/force', [CounterController::class, 'forceDestroy']);
        Route::post('counters/{id}/toggle-published', [CounterController::class, 'togglePublished']);

        // Dashboard (estadísticas del panel)
        Route::get('dashboard/stats', DashboardStatsController::class);

        // Deck attributes configurations (por id, sin publicación ni papelera)
        Route::get('deck-attributes-configurations/for-mode/{gameMode}', [DeckAttributesConfigurationController::class, 'forMode']); // antes de {id}
        Route::get('deck-attributes-configurations', [DeckAttributesConfigurationController::class, 'index']);
        Route::post('deck-attributes-configurations', [DeckAttributesConfigurationController::class, 'store']);
        Route::get('deck-attributes-configurations/{id}', [DeckAttributesConfigurationController::class, 'show']);
        Route::put('deck-attributes-configurations/{id}', [DeckAttributesConfigurationController::class, 'update']);
        Route::delete('deck-attributes-configurations/{id}', [DeckAttributesConfigurationController::class, 'destroy']);

        // Equipment types
        Route::get('equipment-types/options', [EquipmentTypeController::class, 'options']); // antes de {id}
        Route::get('equipment-types', [EquipmentTypeController::class, 'index']);
        Route::post('equipment-types', [EquipmentTypeController::class, 'store']);
        Route::get('equipment-types/{id}', [EquipmentTypeController::class, 'show']);
        Route::put('equipment-types/{id}', [EquipmentTypeController::class, 'update']);
        Route::delete('equipment-types/{id}', [EquipmentTypeController::class, 'destroy']);
        Route::post('equipment-types/{id}/restore', [EquipmentTypeController::class, 'restore']);
        Route::delete('equipment-types/{id}/force', [EquipmentTypeController::class, 'forceDestroy']);

        // Equipment subtypes
        Route::get('equipment-subtypes/options', [EquipmentSubtypeController::class, 'options']); // antes de {id}
        Route::get('equipment-subtypes', [EquipmentSubtypeController::class, 'index']);
        Route::post('equipment-subtypes', [EquipmentSubtypeController::class, 'store']);
        Route::get('equipment-subtypes/{id}', [EquipmentSubtypeController::class, 'show']);
        Route::put('equipment-subtypes/{id}', [EquipmentSubtypeController::class, 'update']);
        Route::delete('equipment-subtypes/{id}', [EquipmentSubtypeController::class, 'destroy']);
        Route::post('equipment-subtypes/{id}/restore', [EquipmentSubtypeController::class, 'restore']);
        Route::delete('equipment-subtypes/{id}/force', [EquipmentSubtypeController::class, 'forceDestroy']);

        // Faction decks (+ editor de cartas y héroes de la single)
        Route::get('faction-decks', [FactionDeckController::class, 'index']);
        Route::post('faction-decks', [FactionDeckController::class, 'store']);
        Route::get('faction-decks/{slug}', [FactionDeckController::class, 'show']);
        Route::put('faction-decks/{slug}', [FactionDeckController::class, 'update']);
        Route::put('faction-decks/{slug}/cards', [FactionDeckController::class, 'updateCards']);
        Route::put('faction-decks/{slug}/heroes', [FactionDeckController::class, 'updateHeroes']);
        Route::delete('faction-decks/{slug}', [FactionDeckController::class, 'destroy']);
        Route::post('faction-decks/{id}/restore', [FactionDeckController::class, 'restore']);
        Route::delete('faction-decks/{id}/force', [FactionDeckController::class, 'forceDestroy']);
        Route::post('faction-decks/{slug}/toggle-published', [FactionDeckController::class, 'togglePublished']);

        // Factions
        Route::get('factions/options', [FactionController::class, 'options']); // antes de {slug}
        Route::get('factions', [FactionController::class, 'index']);
        Route::post('factions', [FactionController::class, 'store']);
        Route::get('factions/{slug}', [FactionController::class, 'show']);
        Route::put('factions/{slug}', [FactionController::class, 'update']);
        Route::delete('factions/{slug}', [FactionController::class, 'destroy']);
        Route::post('factions/{id}/restore', [FactionController::class, 'restore']);
        Route::delete('factions/{id}/force', [FactionController::class, 'forceDestroy']);
        Route::post('factions/{slug}/toggle-published', [FactionController::class, 'togglePublished']);

        // Game modes
        Route::get('game-modes/options', [GameModeController::class, 'options']); // antes de {id}
        Route::get('game-modes', [GameModeController::class, 'index']);
        Route::post('game-modes', [GameModeController::class, 'store']);
        Route::get('game-modes/{id}', [GameModeController::class, 'show']);
        Route::put('game-modes/{id}', [GameModeController::class, 'update']);
        Route::delete('game-modes/{id}', [GameModeController::class, 'destroy']);
        Route::post('game-modes/{id}/restore', [GameModeController::class, 'restore']);
        Route::delete('game-modes/{id}/force', [GameModeController::class, 'forceDestroy']);

        // Hero abilities (por id, sin publicación)
        Route::get('hero-abilities/options', [HeroAbilityController::class, 'options']); // antes de {id}
        Route::get('hero-abilities', [HeroAbilityController::class, 'index']);
        Route::post('hero-abilities', [HeroAbilityController::class, 'store']);
        Route::put('hero-abilities/{id}', [HeroAbilityController::class, 'update']);
        Route::delete('hero-abilities/{id}', [HeroAbilityController::class, 'destroy']);
        Route::post('hero-abilities/{id}/restore', [HeroAbilityController::class, 'restore']);
        Route::delete('hero-abilities/{id}/force', [HeroAbilityController::class, 'forceDestroy']);

        // Hero attributes configuration (singleton: GET + PUT, sin resource)
        Route::get('hero-attributes-configuration', [HeroAttributesConfigurationController::class, 'show']);
        Route::put('hero-attributes-configuration', [HeroAttributesConfigurationController::class, 'update']);

        // Hero classes
        Route::get('hero-classes/options', [HeroClassController::class, 'options']); // antes de {id}
        Route::get('hero-classes', [HeroClassController::class, 'index']);
        Route::post('hero-classes', [HeroClassController::class, 'store']);
        Route::put('hero-classes/{id}', [HeroClassController::class, 'update']);
        Route::delete('hero-classes/{id}', [HeroClassController::class, 'destroy']);
        Route::post('hero-classes/{id}/restore', [HeroClassController::class, 'restore']);
        Route::delete('hero-classes/{id}/force', [HeroClassController::class, 'forceDestroy']);

        // Hero races
        Route::get('hero-races/options', [HeroRaceController::class, 'options']); // antes de {id}
        Route::get('hero-races', [HeroRaceController::class, 'index']);
        Route::post('hero-races', [HeroRaceController::class, 'store']);
        Route::put('hero-races/{id}', [HeroRaceController::class, 'update']);
        Route::delete('hero-races/{id}', [HeroRaceController::class, 'destroy']);
        Route::post('hero-races/{id}/restore', [HeroRaceController::class, 'restore']);
        Route::delete('hero-races/{id}/force', [HeroRaceController::class, 'forceDestroy']);

        // Hero superclasses
        Route::get('hero-superclasses/options', [HeroSuperclassController::class, 'options']); // antes de {id}
        Route::get('hero-superclasses', [HeroSuperclassController::class, 'index']);
        Route::post('hero-superclasses', [HeroSuperclassController::class, 'store']);
        Route::put('hero-superclasses/{id}', [HeroSuperclassController::class, 'update']);
        Route::delete('hero-superclasses/{id}', [HeroSuperclassController::class, 'destroy']);
        Route::post('hero-superclasses/{id}/restore', [HeroSuperclassController::class, 'restore']);
        Route::delete('hero-superclasses/{id}/force', [HeroSuperclassController::class, 'forceDestroy']);

        // Heroes
        Route::get('heroes/options', [HeroController::class, 'options']); // antes de {slug}
        Route::get('heroes', [HeroController::class, 'index']);
        Route::post('heroes', [HeroController::class, 'store']);
        Route::get('heroes/{slug}', [HeroController::class, 'show']);
        Route::put('heroes/{slug}', [HeroController::class, 'update']);
        Route::delete('heroes/{slug}', [HeroController::class, 'destroy']);
        Route::post('heroes/{id}/restore', [HeroController::class, 'restore']);
        Route::delete('heroes/{id}/force', [HeroController::class, 'forceDestroy']);
        Route::post('heroes/{slug}/toggle-published', [HeroController::class, 'togglePublished']);
    });

/*
| TEMPORAL — banco de pruebas /test de la app: datos de render SIN token para
| inspeccionar los componentes en local (ids fijos: héroes 1 y 2, cartas 15 y
| 24). BORRAR junto con app/src/views/TestRenderView.vue y la ruta /test del
| router de la app.
*/
Route::get('test-render', function (Request $request) {
    $locale = (string) $request->query('locale', config('app.locale'));
    $fixed = [
        ['hero', Hero::class, [1, 2]],
        ['card', Card::class, [15, 24]],
    ];
    $items = [];
    foreach ($fixed as [$entity, $model, $ids]) {
        foreach ($ids as $id) {
            if ($m = $model::find($id)) {
                $items[] = ['entity' => $entity, 'id' => $id, 'item' => $m->renderData($locale)];
            }
        }
    }

    return response()->json(['data' => $items]);
});

/*
| TEMPORAL — banco de pruebas /test de la app: datos de render SIN token para
| inspeccionar los componentes en local (ids fijos: héroes 1 y 2, cartas 15 y
| 89). BORRAR junto con app/src/views/TestRenderView.vue y la ruta /test del
| router de la app.
*/
Route::get('test-render', function (Request $request) {
    $locale = (string) $request->query('locale', config('app.locale'));
    $fixed = [
        ['hero', Hero::class, [1, 2]],
        ['card', Card::class, [15, 89]],
    ];
    $items = [];
    foreach ($fixed as [$entity, $model, $ids]) {
        foreach ($ids as $id) {
            if ($m = $model::find($id)) {
                $items[] = ['entity' => $entity, 'id' => $id, 'item' => $m->renderData($locale)];
            }
        }
    }

    return response()->json(['data' => $items]);
});
