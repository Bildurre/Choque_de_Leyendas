<?php

use App\Models\Card;
use App\Models\CardType;
use App\Models\Faction;
use App\Models\FactionDeck;
use App\Models\GameMode;
use App\Models\Hero;
use App\Models\HeroAbility;
use App\Models\HeroClass;
use App\Models\HeroSuperclass;
use Illuminate\Support\Facades\Route;

/*
| Helpers de los tests de la web pública. Se cargan con require_once desde
| cada fichero de test (no es un test: PHPUnit solo carga *Test.php).
*/

if (! function_exists('ensurePublicApiRoutes')) {
    /**
     * Registra las rutas del fragmento api-publica si aún no están integradas
     * en routes/api.php (fase de staging). Tras el ensamblaje el fragmento
     * desaparece pero las rutas ya viven en el fichero real: no-op.
     */
    function ensurePublicApiRoutes(): void
    {
        $registered = collect(app('router')->getRoutes()->getRoutes())->contains(
            fn ($route) => $route->uri() === 'api/factions' && in_array('GET', $route->methods(), true),
        );

        if ($registered) {
            return;
        }

        $fragment = dirname(base_path()).'/.staging-public/fragments/api-publica/routes.php';
        Route::middleware('api')->prefix('api')->group($fragment);
    }

    /** Facción mínima (publicada por defecto); slugs es/en desde el nombre. */
    function publicFaction(array $overrides = []): Faction
    {
        $faction = new Faction;
        $faction->setTranslations('name', $overrides['name'] ?? ['es' => 'Alianza', 'en' => 'Alliance']);
        $faction->setTranslations('lore_text', ['es' => '<p>Trasfondo</p>', 'en' => '<p>Lore</p>']);
        $faction->setTranslations('epic_quote', ['es' => 'Cita épica', 'en' => 'Epic quote']);
        $faction->color = $overrides['color'] ?? '#336699';
        $faction->is_published = $overrides['is_published'] ?? true;
        $faction->save();

        return $faction;
    }

    /** Tipo de carta con flags configurables. */
    function publicCardType(array $overrides = []): CardType
    {
        $type = new CardType;
        $type->setTranslations('name', $overrides['name'] ?? ['es' => 'Técnica', 'en' => 'Technique']);
        $type->allows_subtypes = $overrides['allows_subtypes'] ?? false;
        $type->is_equipment = $overrides['is_equipment'] ?? false;
        $type->save();

        return $type;
    }

    /** Carta mínima (publicada por defecto). */
    function publicCard(array $overrides = []): Card
    {
        $card = new Card;
        $card->setTranslations('name', $overrides['name'] ?? ['es' => 'Espada corta', 'en' => 'Short sword']);
        $card->setTranslations('effect', ['es' => 'Golpea dos veces.', 'en' => 'Strikes twice.']);
        $card->setTranslations('lore_text', ['es' => '<p>Forjada en EdC.</p>', 'en' => '<p>Forged in EdC.</p>']);
        $card->faction_id = $overrides['faction_id'] ?? null;
        $card->card_type_id = $overrides['card_type_id'] ?? publicCardType()->id;
        $card->hero_ability_id = $overrides['hero_ability_id'] ?? null;
        $card->cost = $overrides['cost'] ?? 'RG';
        $card->hands = $overrides['hands'] ?? null;
        $card->is_unique = $overrides['is_unique'] ?? false;
        $card->is_published = $overrides['is_published'] ?? true;
        $card->save();

        return $card;
    }

    /** Clase de héroe con superclase y pasiva de clase. */
    function publicHeroClass(): HeroClass
    {
        $superclass = new HeroSuperclass;
        $superclass->setTranslations('name', ['es' => 'Luchador', 'en' => 'Fighter']);
        $superclass->save();

        $class = new HeroClass;
        $class->setTranslations('name', ['es' => 'Guerrero', 'en' => 'Warrior']);
        $class->setTranslations('passive', ['es' => 'Pasiva de clase', 'en' => 'Class passive']);
        $class->hero_superclass_id = $superclass->id;
        $class->save();

        return $class;
    }

    /** Habilidad activa con coste y descripción. */
    function publicAbility(array $overrides = []): HeroAbility
    {
        $ability = new HeroAbility;
        $ability->setTranslations('name', $overrides['name'] ?? ['es' => 'Golpe certero', 'en' => 'True strike']);
        $ability->setTranslations('description', ['es' => 'Hace daño.', 'en' => 'Deals damage.']);
        $ability->cost = $overrides['cost'] ?? 'RB';
        $ability->save();

        return $ability;
    }

    /** Héroe mínimo (publicado por defecto). */
    function publicHero(array $overrides = []): Hero
    {
        $hero = new Hero;
        $hero->setTranslations('name', $overrides['name'] ?? ['es' => 'Aritz', 'en' => 'Aritz the Bold']);
        $hero->setTranslations('lore_text', ['es' => '<p>Nació en el norte.</p>', 'en' => '<p>Born up north.</p>']);
        $hero->setTranslations('epic_quote', ['es' => 'Por la Alianza', 'en' => 'For the Alliance']);
        $hero->faction_id = $overrides['faction_id'] ?? null;
        $hero->hero_class_id = $overrides['hero_class_id'] ?? null;
        $hero->agility = $overrides['agility'] ?? 3;
        $hero->mental = $overrides['mental'] ?? 2;
        $hero->will = $overrides['will'] ?? 4;
        $hero->strength = $overrides['strength'] ?? 3;
        $hero->armor = $overrides['armor'] ?? 2;
        $hero->is_published = $overrides['is_published'] ?? true;
        $hero->save();

        return $hero;
    }

    /** Modo de juego mínimo. */
    function publicGameMode(): GameMode
    {
        $mode = new GameMode;
        $mode->setTranslations('name', ['es' => 'Escaramuza', 'en' => 'Skirmish']);
        $mode->save();

        return $mode;
    }

    /** Mazo mínimo (publicado por defecto). */
    function publicDeck(array $overrides = []): FactionDeck
    {
        $deck = new FactionDeck;
        $deck->setTranslations('name', $overrides['name'] ?? ['es' => 'Mazo inicial', 'en' => 'Starter deck']);
        $deck->setTranslations('description', ['es' => '<p>Para empezar.</p>', 'en' => '<p>To get going.</p>']);
        $deck->setTranslations('epic_quote', ['es' => 'Al combate', 'en' => 'To battle']);
        $deck->game_mode_id = $overrides['game_mode_id'] ?? null;
        $deck->is_published = $overrides['is_published'] ?? true;
        $deck->save();

        return $deck;
    }
}
