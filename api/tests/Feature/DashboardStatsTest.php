<?php

use App\Models\Card;
use App\Models\CardType;
use App\Models\Faction;
use App\Models\FactionDeck;
use App\Models\GameMode;
use App\Models\Hero;

// Estadísticas del panel: solo admin, estructura por secciones, agregados
// (totales, curva de coste, colores) y nombres localizados por ?locale.

require_once __DIR__.'/Public/Helpers.php';

function dashFaction(array $names, string $color = '#aa3344', bool $published = true): Faction
{
    $faction = new Faction;
    $faction->setTranslations('name', $names);
    $faction->color = $color;
    $faction->is_published = $published;
    $faction->save();

    return $faction;
}

function dashCard(CardType $type, ?Faction $faction, ?string $cost, bool $published = false): Card
{
    $card = new Card;
    $card->setTranslations('name', ['es' => 'Carta '.uniqid()]);
    $card->card_type_id = $type->id;
    $card->faction_id = $faction?->id;
    $card->cost = $cost;
    $card->is_published = $published;
    $card->save();

    return $card;
}

function dashHero(Faction $faction, string $gender = 'male', int $agility = 3): Hero
{
    $hero = new Hero;
    $hero->setTranslations('name', ['es' => 'Héroe '.uniqid()]);
    $hero->faction_id = $faction->id;
    // Raza y clase (con superclase) ya son obligatorias: mínimas por héroe.
    $hero->hero_race_id = publicHeroRace(['name' => ['es' => 'Raza '.uniqid()]])->id;
    $hero->hero_class_id = publicHeroClass(['name' => ['es' => 'Clase '.uniqid()]])->id;
    $hero->gender = $gender;
    $hero->agility = $agility;
    $hero->save();

    return $hero;
}

it('devuelve las estadísticas por secciones con los agregados', function () {
    $admin = motorUser('admin');

    $faction = dashFaction(['es' => 'Imperio']);
    $draftFaction = dashFaction(['es' => 'Horda'], '#33aa44', published: false);

    $type = new CardType;
    $type->setTranslations('name', ['es' => 'Ataque']);
    $type->save();

    // 3 cartas: costes RRG (3 dados), B (1 dado) y sin coste (0 dados).
    dashCard($type, $faction, 'RRG', published: true);
    dashCard($type, $faction, 'B');
    dashCard($type, $draftFaction, null);

    dashHero($faction, 'male', agility: 2);
    dashHero($faction, 'female', agility: 4);

    $mode = new GameMode;
    $mode->setTranslations('name', ['es' => 'Estándar']);
    $mode->save();

    $deck = new FactionDeck;
    $deck->setTranslations('name', ['es' => 'Mazo imperial']);
    $deck->game_mode_id = $mode->id;
    $deck->save();
    $deck->factions()->sync([$faction->id]);

    $response = $this->actingAs($admin)->getJson('/api/admin/dashboard/stats')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'totals' => [
                    'factions' => ['total', 'published'],
                    'heroes' => ['total', 'published'],
                    'cards' => ['total', 'published'],
                    'faction_decks' => ['total', 'published'],
                    'counters' => ['total', 'published'],
                    'hero_abilities' => ['total'],
                    'game_modes' => ['total'],
                ],
                'factions',
                'cards' => [
                    'by_type', 'cost_curve', 'cost_colors', 'avg_cost',
                    'attack_types', 'equipment', 'area', 'unique',
                ],
                'heroes' => ['by_superclass', 'by_race', 'gender', 'attributes'],
                'decks' => ['by_game_mode', 'avg_cards', 'avg_heroes'],
            ],
        ]);

    // Totales con publicadas.
    $response->assertJsonPath('data.totals.factions', ['total' => 2, 'published' => 1])
        ->assertJsonPath('data.totals.cards', ['total' => 3, 'published' => 1])
        ->assertJsonPath('data.totals.heroes', ['total' => 2, 'published' => 0]);

    // Comparativa por facción, ordenada por cartas y con su color.
    $imperio = collect($response->json('data.factions'))->firstWhere('name', 'Imperio');
    expect($response->json('data.factions.0.name'))->toBe('Imperio')
        ->and($imperio['color'])->toBe('#aa3344')
        ->and($imperio['heroes'])->toBe(2)
        ->and($imperio['cards'])->toBe(2)
        ->and($imperio['decks'])->toBe(1);

    // Curva de coste (0..5 dados) y cartas con dados de cada color.
    $curve = collect($response->json('data.cards.cost_curve'))->pluck('count', 'dice');
    expect($curve[0])->toBe(1)->and($curve[1])->toBe(1)->and($curve[3])->toBe(1)
        ->and($response->json('data.cards.cost_colors'))->toBe(['R' => 1, 'G' => 1, 'B' => 1])
        ->and($response->json('data.cards.by_type.0.name'))->toBe('Ataque')
        ->and($response->json('data.cards.by_type.0.count'))->toBe(3);

    // Género y atributos (agility 2 y 4 ⇒ media 3, mín 2, máx 4).
    expect($response->json('data.heroes.gender'))->toBe(['male' => 1, 'female' => 1])
        ->and($response->json('data.heroes.attributes.agility'))->toBe(['avg' => 3, 'min' => 2, 'max' => 4]);

    // Mazos por modo.
    expect($response->json('data.decks.by_game_mode.0.name'))->toBe('Estándar')
        ->and($response->json('data.decks.by_game_mode.0.count'))->toBe(1);
});

it('localiza los nombres al locale de la petición', function () {
    $admin = motorUser('admin');
    dashFaction(['es' => 'Imperio', 'en' => 'Empire']);

    $this->actingAs($admin)->getJson('/api/admin/dashboard/stats?locale=en')
        ->assertOk()
        ->assertJsonPath('data.factions.0.name', 'Empire');
});

it('solo los admin acceden a las estadísticas', function () {
    $this->getJson('/api/admin/dashboard/stats')->assertUnauthorized();

    $user = motorUser('user');
    $this->actingAs($user)->getJson('/api/admin/dashboard/stats')->assertForbidden();
});
