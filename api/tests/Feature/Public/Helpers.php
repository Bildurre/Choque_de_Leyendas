<?php

use App\Models\AttackRange;
use App\Models\AttackSubtype;
use App\Models\Card;
use App\Models\CardSubtype;
use App\Models\CardType;
use App\Models\Counter;
use App\Models\EquipmentSubtype;
use App\Models\EquipmentType;
use App\Models\Faction;
use App\Models\FactionDeck;
use App\Models\GameMode;
use App\Models\Hero;
use App\Models\HeroAbility;
use App\Models\HeroClass;
use App\Models\HeroRace;
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
        $faction->setTranslations('lore_text', $overrides['lore_text'] ?? ['es' => '<p>Trasfondo</p>', 'en' => '<p>Lore</p>']);
        $faction->setTranslations('epic_quote', $overrides['epic_quote'] ?? ['es' => 'Cita épica', 'en' => 'Epic quote']);
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
        $card->setTranslations('effect', $overrides['effect'] ?? ['es' => 'Golpea dos veces.', 'en' => 'Strikes twice.']);
        $card->setTranslations('lore_text', $overrides['lore_text'] ?? ['es' => '<p>Forjada en EdC.</p>', 'en' => '<p>Forged in EdC.</p>']);
        // Opcionales: solo se rellenan si el test los pasa (por defecto quedan null)
        if (isset($overrides['restriction'])) {
            $card->setTranslations('restriction', $overrides['restriction']);
        }
        if (isset($overrides['epic_quote'])) {
            $card->setTranslations('epic_quote', $overrides['epic_quote']);
        }
        // La facción ya es obligatoria: si el test no la pasa se crea una
        // SIN publicar (no contamina los índices/filtros públicos).
        $card->faction_id = $overrides['faction_id']
            ?? publicFaction(['name' => ['es' => 'Facción de pruebas', 'en' => 'Test faction'], 'is_published' => false])->id;
        $card->card_type_id = $overrides['card_type_id'] ?? publicCardType()->id;
        $card->card_subtype_id = $overrides['card_subtype_id'] ?? null;
        $card->equipment_type_id = $overrides['equipment_type_id'] ?? null;
        $card->equipment_subtype_id = $overrides['equipment_subtype_id'] ?? null;
        $card->attack_type = $overrides['attack_type'] ?? null;
        $card->attack_range_id = $overrides['attack_range_id'] ?? null;
        $card->attack_subtype_id = $overrides['attack_subtype_id'] ?? null;
        $card->hero_ability_id = $overrides['hero_ability_id'] ?? null;
        // array_key_exists y no ??: 'cost' => null significa carta sin coste
        $card->cost = array_key_exists('cost', $overrides) ? $overrides['cost'] : 'RG';
        $card->hands = $overrides['hands'] ?? null;
        $card->area = $overrides['area'] ?? false;
        $card->is_unique = $overrides['is_unique'] ?? false;
        $card->is_published = $overrides['is_published'] ?? true;
        $card->save();

        return $card;
    }

    /** Subtipo de carta mínimo. */
    function publicCardSubtype(array $overrides = []): CardSubtype
    {
        $subtype = new CardSubtype;
        $subtype->setTranslations('name', $overrides['name'] ?? ['es' => 'Bestia', 'en' => 'Beast']);
        $subtype->save();

        return $subtype;
    }

    /** Tipo de equipo mínimo (Arma: lleva manos por defecto). */
    function publicEquipmentType(array $overrides = []): EquipmentType
    {
        $type = new EquipmentType;
        $type->setTranslations('name', $overrides['name'] ?? ['es' => 'Arma', 'en' => 'Weapon']);
        $type->uses_hands = $overrides['uses_hands'] ?? true;
        $type->save();

        return $type;
    }

    /** Subtipo de equipo mínimo (crea su tipo si no se pasa). */
    function publicEquipmentSubtype(array $overrides = []): EquipmentSubtype
    {
        $subtype = new EquipmentSubtype;
        $subtype->setTranslations('name', $overrides['name'] ?? ['es' => 'Espada', 'en' => 'Sword']);
        $subtype->equipment_type_id = $overrides['equipment_type_id'] ?? publicEquipmentType()->id;
        $subtype->save();

        return $subtype;
    }

    /** Rango de ataque mínimo. */
    function publicAttackRange(array $overrides = []): AttackRange
    {
        $range = new AttackRange;
        $range->setTranslations('name', $overrides['name'] ?? ['es' => 'Cuerpo a cuerpo', 'en' => 'Melee']);
        $range->save();

        return $range;
    }

    /** Subtipo de ataque mínimo. */
    function publicAttackSubtype(array $overrides = []): AttackSubtype
    {
        $subtype = new AttackSubtype;
        $subtype->setTranslations('name', $overrides['name'] ?? ['es' => 'Corte', 'en' => 'Slash']);
        $subtype->save();

        return $subtype;
    }

    /** Raza de héroe mínima. */
    function publicHeroRace(array $overrides = []): HeroRace
    {
        $race = new HeroRace;
        $race->setTranslations('name', $overrides['name'] ?? ['es' => 'Humano', 'en' => 'Human']);
        $race->save();

        return $race;
    }

    /** Clase de héroe con superclase (propia o dada) y pasiva de clase. */
    function publicHeroClass(array $overrides = []): HeroClass
    {
        if (! array_key_exists('hero_superclass_id', $overrides)) {
            $superclass = new HeroSuperclass;
            $superclass->setTranslations('name', $overrides['superclass_name'] ?? ['es' => 'Luchador', 'en' => 'Fighter']);
            $superclass->save();
            $overrides['hero_superclass_id'] = $superclass->id;
        }

        $class = new HeroClass;
        $class->setTranslations('name', $overrides['name'] ?? ['es' => 'Guerrero', 'en' => 'Warrior']);
        $class->setTranslations('passive', ['es' => 'Pasiva de clase', 'en' => 'Class passive']);
        $class->hero_superclass_id = $overrides['hero_superclass_id'];
        $class->save();

        return $class;
    }

    /** Habilidad activa con coste y descripción. */
    function publicAbility(array $overrides = []): HeroAbility
    {
        $ability = new HeroAbility;
        $ability->setTranslations('name', $overrides['name'] ?? ['es' => 'Golpe certero', 'en' => 'True strike']);
        $ability->setTranslations('description', $overrides['description'] ?? ['es' => 'Hace daño.', 'en' => 'Deals damage.']);
        $ability->attack_type = $overrides['attack_type'] ?? null;
        $ability->attack_range_id = $overrides['attack_range_id'] ?? null;
        $ability->attack_subtype_id = $overrides['attack_subtype_id'] ?? null;
        $ability->area = $overrides['area'] ?? false;
        $ability->cost = $overrides['cost'] ?? 'RB';
        $ability->save();

        return $ability;
    }

    /** Héroe mínimo (publicado por defecto). */
    function publicHero(array $overrides = []): Hero
    {
        $hero = new Hero;
        $hero->setTranslations('name', $overrides['name'] ?? ['es' => 'Aritz', 'en' => 'Aritz the Bold']);
        $hero->setTranslations('lore_text', $overrides['lore_text'] ?? ['es' => '<p>Nació en el norte.</p>', 'en' => '<p>Born up north.</p>']);
        $hero->setTranslations('epic_quote', $overrides['epic_quote'] ?? ['es' => 'Por la Alianza', 'en' => 'For the Alliance']);
        // Opcionales: solo se rellenan si el test los pasa (por defecto quedan null)
        if (isset($overrides['passive_name'])) {
            $hero->setTranslations('passive_name', $overrides['passive_name']);
        }
        if (isset($overrides['passive_description'])) {
            $hero->setTranslations('passive_description', $overrides['passive_description']);
        }
        // Facción, raza y clase ya son obligatorias: si el test no las pasa
        // se crean mínimas (facción SIN publicar para no contaminar los
        // índices/filtros públicos).
        $hero->faction_id = $overrides['faction_id']
            ?? publicFaction(['name' => ['es' => 'Facción de pruebas', 'en' => 'Test faction'], 'is_published' => false])->id;
        $hero->hero_class_id = $overrides['hero_class_id'] ?? publicHeroClass()->id;
        $hero->hero_race_id = $overrides['hero_race_id'] ?? publicHeroRace()->id;
        $hero->agility = $overrides['agility'] ?? 3;
        $hero->mental = $overrides['mental'] ?? 2;
        $hero->will = $overrides['will'] ?? 4;
        $hero->strength = $overrides['strength'] ?? 3;
        $hero->armor = $overrides['armor'] ?? 2;
        $hero->is_published = $overrides['is_published'] ?? true;
        $hero->save();

        return $hero;
    }

    /** Contador mínimo (publicado por defecto; sin slug, va por id). */
    function publicCounter(array $overrides = []): Counter
    {
        $counter = new Counter;
        $counter->setTranslations('name', $overrides['name'] ?? ['es' => 'Veneno', 'en' => 'Poison']);
        $counter->setTranslations('effect', $overrides['effect'] ?? ['es' => 'Pierde 1 de vida.', 'en' => 'Lose 1 health.']);
        $counter->type = $overrides['type'] ?? 'bane';
        $counter->is_published = $overrides['is_published'] ?? true;
        $counter->save();

        return $counter;
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
        $deck->setTranslations('description', $overrides['description'] ?? ['es' => '<p>Para empezar.</p>', 'en' => '<p>To get going.</p>']);
        $deck->setTranslations('epic_quote', $overrides['epic_quote'] ?? ['es' => 'Al combate', 'en' => 'To battle']);
        // El modo de juego ya es obligatorio: si el test no lo pasa se crea uno.
        $deck->game_mode_id = $overrides['game_mode_id'] ?? publicGameMode()->id;
        $deck->is_published = $overrides['is_published'] ?? true;
        $deck->save();

        return $deck;
    }
}
