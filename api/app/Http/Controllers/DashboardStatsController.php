<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\CardType;
use App\Models\Counter;
use App\Models\Faction;
use App\Models\FactionDeck;
use App\Models\GameMode;
use App\Models\Hero;
use App\Models\HeroAbility;
use App\Models\HeroRace;
use App\Models\HeroSuperclass;
use Illuminate\Support\Facades\DB;

/**
 * Estadísticas del panel (portado del dashboard del viejo): totales por
 * entidad, comparativa por facción, cartas (tipos, curva de coste, colores),
 * habilidades (curva de coste, colores, ataque), héroes (superclases, razas,
 * género, atributos) y mazos por modo. Todo con
 * agregados en BBDD (count/groupBy/avg, nada de cargar tablas enteras) y
 * nombres localizados al locale de la petición (SetLocale).
 */
class DashboardStatsController extends Controller
{
    public function __invoke()
    {
        $locale = app()->getLocale();

        return response()->json([
            'data' => [
                'totals' => $this->totals(),
                'factions' => $this->factions($locale),
                'cards' => $this->cards($locale),
                'abilities' => $this->abilities(),
                'heroes' => $this->heroes($locale),
                'decks' => $this->decks($locale),
            ],
        ]);
    }

    /** Total y publicados por entidad (los que no publican, solo total). */
    protected function totals(): array
    {
        $publishable = [
            'factions' => Faction::query(),
            'heroes' => Hero::query(),
            'cards' => Card::query(),
            'faction_decks' => FactionDeck::query(),
            'counters' => Counter::query(),
        ];

        $totals = [];
        foreach ($publishable as $key => $query) {
            $row = $query->selectRaw(
                'count(*) as total, coalesce(sum(case when is_published then 1 else 0 end), 0) as published'
            )->first();
            $totals[$key] = ['total' => (int) $row->total, 'published' => (int) $row->published];
        }

        $totals['hero_abilities'] = ['total' => HeroAbility::count()];
        $totals['game_modes'] = ['total' => GameMode::count()];

        return $totals;
    }

    /** Comparativa por facción: héroes, cartas y mazos, con su color. */
    protected function factions(string $locale): array
    {
        return Faction::withCount(['heroes', 'cards', 'factionDecks'])
            ->orderByDesc('cards_count')
            ->get()
            ->map(fn (Faction $faction) => [
                'id' => $faction->id,
                'name' => $faction->getTranslation('name', $locale),
                'color' => $faction->color,
                'is_published' => (bool) $faction->is_published,
                'heroes' => (int) $faction->heroes_count,
                'cards' => (int) $faction->cards_count,
                'decks' => (int) $faction->faction_decks_count,
            ])
            ->values()
            ->all();
    }

    /** Cartas: por tipo, curva de coste, colores, ataque, equipo y flags. */
    protected function cards(string $locale): array
    {
        // Por tipo (todos los tipos, también sin cartas), con publicadas.
        $byTypeCounts = Card::query()
            ->selectRaw(
                'card_type_id, count(*) as total,'
                .'coalesce(sum(case when is_published then 1 else 0 end), 0) as published'
            )
            ->groupBy('card_type_id')
            ->get()
            ->keyBy('card_type_id');

        $byType = CardType::orderBy('id')->get()
            ->map(fn (CardType $type) => [
                'id' => $type->id,
                'name' => $type->getTranslation('name', $locale),
                'count' => (int) ($byTypeCounts[$type->id]->total ?? 0),
                'published' => (int) ($byTypeCounts[$type->id]->published ?? 0),
            ])
            ->sortByDesc('count')
            ->values()
            ->all();

        // Curva de coste por nº de dados: cost canónico ⇒ length = dados.
        $byDice = Card::query()
            ->selectRaw('coalesce(length(cost), 0) as dice, count(*) as total')
            ->groupBy('dice')
            ->pluck('total', 'dice');

        $costCurve = [];
        for ($dice = 0; $dice <= Card::COST_MAX; $dice++) {
            $costCurve[] = ['dice' => $dice, 'count' => (int) ($byDice[$dice] ?? 0)];
        }

        // Cartas con al menos un dado de cada color + medias/flags, una pasada.
        $agg = Card::query()->selectRaw(
            "coalesce(sum(case when cost like '%R%' then 1 else 0 end), 0) as red,"
            ."coalesce(sum(case when cost like '%G%' then 1 else 0 end), 0) as green,"
            ."coalesce(sum(case when cost like '%B%' then 1 else 0 end), 0) as blue,"
            .'coalesce(avg(coalesce(length(cost), 0)), 0) as avg_cost,'
            .'coalesce(sum(case when area then 1 else 0 end), 0) as area,'
            .'coalesce(sum(case when is_unique then 1 else 0 end), 0) as uniques'
        )->first();

        // Físico/mágico (solo cartas con tipo de ataque).
        $attackTypes = Card::query()
            ->whereNotNull('attack_type')
            ->selectRaw('attack_type, count(*) as total')
            ->groupBy('attack_type')
            ->pluck('total', 'attack_type');

        // Equipo por categoría (armas = tipos con manos; el resto, armaduras).
        $equipment = Card::query()
            ->join('equipment_types', 'equipment_types.id', '=', 'cards.equipment_type_id')
            ->whereNull('equipment_types.deleted_at')
            ->selectRaw("case when equipment_types.uses_hands then 'weapon' else 'armor' end as category, count(*) as total")
            ->groupBy('equipment_types.uses_hands')
            ->pluck('total', 'category');

        return [
            'by_type' => $byType,
            'cost_curve' => $costCurve,
            'cost_colors' => [
                'R' => (int) $agg->red,
                'G' => (int) $agg->green,
                'B' => (int) $agg->blue,
            ],
            'avg_cost' => round((float) $agg->avg_cost, 1),
            'attack_types' => [
                'physical' => (int) ($attackTypes['physical'] ?? 0),
                'magical' => (int) ($attackTypes['magical'] ?? 0),
            ],
            'equipment' => [
                'weapon' => (int) ($equipment['weapon'] ?? 0),
                'armor' => (int) ($equipment['armor'] ?? 0),
            ],
            'area' => (int) $agg->area,
            'unique' => (int) $agg->uniques,
        ];
    }

    /** Habilidades: curva de coste, colores y ataque (como las cartas). */
    protected function abilities(): array
    {
        // Curva de coste por nº de dados: cost canónico ⇒ length = dados.
        $byDice = HeroAbility::query()
            ->selectRaw('coalesce(length(cost), 0) as dice, count(*) as total')
            ->groupBy('dice')
            ->pluck('total', 'dice');

        $costCurve = [];
        for ($dice = 0; $dice <= HeroAbility::COST_MAX; $dice++) {
            $costCurve[] = ['dice' => $dice, 'count' => (int) ($byDice[$dice] ?? 0)];
        }

        // Habilidades con al menos un dado de cada color + media/área, una pasada.
        $agg = HeroAbility::query()->selectRaw(
            "coalesce(sum(case when cost like '%R%' then 1 else 0 end), 0) as red,"
            ."coalesce(sum(case when cost like '%G%' then 1 else 0 end), 0) as green,"
            ."coalesce(sum(case when cost like '%B%' then 1 else 0 end), 0) as blue,"
            .'coalesce(avg(coalesce(length(cost), 0)), 0) as avg_cost,'
            .'coalesce(sum(case when area then 1 else 0 end), 0) as area'
        )->first();

        // Físico/mágico (solo habilidades con tipo de ataque).
        $attackTypes = HeroAbility::query()
            ->whereNotNull('attack_type')
            ->selectRaw('attack_type, count(*) as total')
            ->groupBy('attack_type')
            ->pluck('total', 'attack_type');

        return [
            'cost_curve' => $costCurve,
            'cost_colors' => [
                'R' => (int) $agg->red,
                'G' => (int) $agg->green,
                'B' => (int) $agg->blue,
            ],
            'avg_cost' => round((float) $agg->avg_cost, 1),
            'attack_types' => [
                'physical' => (int) ($attackTypes['physical'] ?? 0),
                'magical' => (int) ($attackTypes['magical'] ?? 0),
            ],
            'area' => (int) $agg->area,
        ];
    }

    /** Héroes: superclases, razas, género y atributos (media/mín/máx). */
    protected function heroes(string $locale): array
    {
        // Por superclase, vía la clase del héroe.
        $superclassCounts = Hero::query()
            ->join('hero_classes', 'hero_classes.id', '=', 'heroes.hero_class_id')
            ->whereNull('hero_classes.deleted_at')
            ->selectRaw('hero_classes.hero_superclass_id as superclass_id, count(*) as total')
            ->groupBy('hero_classes.hero_superclass_id')
            ->pluck('total', 'superclass_id');

        $bySuperclass = HeroSuperclass::orderBy('id')->get()
            ->map(fn (HeroSuperclass $superclass) => [
                'id' => $superclass->id,
                'name' => $superclass->getTranslation('name', $locale),
                'count' => (int) ($superclassCounts[$superclass->id] ?? 0),
            ])
            ->sortByDesc('count')
            ->values()
            ->all();

        // Por raza.
        $raceCounts = Hero::query()
            ->whereNotNull('hero_race_id')
            ->selectRaw('hero_race_id, count(*) as total')
            ->groupBy('hero_race_id')
            ->pluck('total', 'hero_race_id');

        $byRace = HeroRace::orderBy('id')->get()
            ->map(fn (HeroRace $race) => [
                'id' => $race->id,
                'name' => $race->getTranslation('name', $locale),
                'count' => (int) ($raceCounts[$race->id] ?? 0),
            ])
            ->sortByDesc('count')
            ->values()
            ->all();

        $genderCounts = Hero::query()
            ->selectRaw('gender, count(*) as total')
            ->groupBy('gender')
            ->pluck('total', 'gender');

        // Media/mín/máx de cada atributo en una sola query.
        $selects = [];
        foreach (['agility', 'mental', 'will', 'strength', 'armor'] as $attribute) {
            $selects[] = "avg({$attribute}) as {$attribute}_avg,"
                ."min({$attribute}) as {$attribute}_min,"
                ."max({$attribute}) as {$attribute}_max";
        }
        $row = Hero::query()->selectRaw(implode(',', $selects))->first();

        $attributes = [];
        foreach (['agility', 'mental', 'will', 'strength', 'armor'] as $attribute) {
            $attributes[$attribute] = [
                'avg' => round((float) ($row->{"{$attribute}_avg"} ?? 0), 1),
                'min' => (int) ($row->{"{$attribute}_min"} ?? 0),
                'max' => (int) ($row->{"{$attribute}_max"} ?? 0),
            ];
        }

        return [
            'by_superclass' => $bySuperclass,
            'by_race' => $byRace,
            'gender' => [
                'male' => (int) ($genderCounts['male'] ?? 0),
                'female' => (int) ($genderCounts['female'] ?? 0),
            ],
            'attributes' => $attributes,
        ];
    }

    /** Mazos: por modo de juego y tamaño medio (cartas con copias, héroes). */
    protected function decks(string $locale): array
    {
        $modeCounts = FactionDeck::query()
            ->selectRaw(
                'game_mode_id, count(*) as total,'
                .'coalesce(sum(case when is_published then 1 else 0 end), 0) as published'
            )
            ->groupBy('game_mode_id')
            ->get()
            ->keyBy('game_mode_id');

        $byGameMode = GameMode::orderBy('id')->get()
            ->map(fn (GameMode $mode) => [
                'id' => $mode->id,
                'name' => $mode->getTranslation('name', $locale),
                'count' => (int) ($modeCounts[$mode->id]->total ?? 0),
                'published' => (int) ($modeCounts[$mode->id]->published ?? 0),
            ])
            ->sortByDesc('count')
            ->values()
            ->all();

        // Medias solo sobre mazos con contenido, como el viejo (los pivots se
        // agregan en BBDD; se excluyen mazos/cartas/héroes en papelera).
        $cardTotals = DB::table('card_faction_deck')
            ->join('faction_decks', 'faction_decks.id', '=', 'card_faction_deck.faction_deck_id')
            ->join('cards', 'cards.id', '=', 'card_faction_deck.card_id')
            ->whereNull('faction_decks.deleted_at')
            ->whereNull('cards.deleted_at')
            ->groupBy('card_faction_deck.faction_deck_id')
            ->selectRaw('sum(card_faction_deck.copies) as total')
            ->pluck('total');

        $heroTotals = DB::table('faction_deck_hero')
            ->join('faction_decks', 'faction_decks.id', '=', 'faction_deck_hero.faction_deck_id')
            ->join('heroes', 'heroes.id', '=', 'faction_deck_hero.hero_id')
            ->whereNull('faction_decks.deleted_at')
            ->whereNull('heroes.deleted_at')
            ->groupBy('faction_deck_hero.faction_deck_id')
            ->selectRaw('count(*) as total')
            ->pluck('total');

        return [
            'by_game_mode' => $byGameMode,
            'avg_cards' => round((float) ($cardTotals->avg() ?? 0), 1),
            'avg_heroes' => round((float) ($heroTotals->avg() ?? 0), 1),
        ];
    }
}
