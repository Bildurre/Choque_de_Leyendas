<?php

use App\Models\Card;
use App\Models\CardType;
use App\Models\Counter;
use App\Models\Faction;
use App\Models\Hero;
use App\Pdf\CountersExport;
use App\Pdf\FactionExport;
use Edc\Core\Pdf\PdfExportRegistry;

// Exports de PDF del juego: contadores recortables (con tokens de vida) y
// hojas por facción (héroes x1 + cartas x2).

require_once __DIR__.'/Public/Helpers.php';

function makeFaction(string $name, bool $published = true): Faction
{
    $faction = new Faction;
    $faction->setTranslations('name', ['es' => $name]);
    $faction->color = '#aa3355';
    $faction->is_published = $published;
    $faction->save();

    return $faction;
}

it('counters incluye los tokens de vida tras los contadores', function () {
    $counter = new Counter;
    $counter->setTranslations('name', ['es' => 'Bendición']);
    $counter->type = 'boon';
    $counter->is_published = true;
    $counter->save();

    $items = (new CountersExport)->items(null, 'es');

    // 1 contador + 5 tokens de vida (1, 2, 5, 10, 20)
    expect($items)->toHaveCount(6)
        ->and($items[0]->previewable?->is($counter))->toBeTrue();

    $health = array_slice($items, 1);
    expect(array_map(fn ($i) => $i->copies, $health))->toBe([15, 15, 10, 10, 10]);

    foreach ($health as $token) {
        expect($token->image)->toStartWith('data:image/svg+xml;base64,');
    }

    // El SVG del primer token lleva el valor 1 y el corazón.
    $svg = base64_decode(substr($health[0]->image, strlen('data:image/svg+xml;base64,')));
    expect($svg)->toContain('>1</text>')->toContain('<path d="M12,21.35');
});

it('faction imprime héroes x1 y cartas x2 de la facción, solo publicados', function () {
    $faction = makeFaction('Imperio');

    $hero = new Hero;
    $hero->setTranslations('name', ['es' => 'Aitor']);
    $hero->faction_id = $faction->id;
    // Raza y clase ya son obligatorias: mínimas desde los helpers públicos.
    $hero->hero_race_id = publicHeroRace()->id;
    $hero->hero_class_id = publicHeroClass()->id;
    $hero->gender = 'male';
    $hero->is_published = true;
    $hero->save();

    $type = new CardType;
    $type->setTranslations('name', ['es' => 'Acción']);
    $type->save();

    $card = new Card;
    $card->setTranslations('name', ['es' => 'Tajo']);
    $card->faction_id = $faction->id;
    $card->card_type_id = $type->id;
    $card->is_published = true;
    $card->save();

    $draft = new Card;
    $draft->setTranslations('name', ['es' => 'Oculta']);
    $draft->faction_id = $faction->id;
    $draft->card_type_id = $type->id;
    $draft->is_published = false;
    $draft->save();

    $items = (new FactionExport)->items($faction, 'es');

    expect($items)->toHaveCount(2)
        ->and($items[0]->previewable?->is($hero))->toBeTrue()
        ->and($items[0]->copies)->toBe(1)
        ->and($items[1]->previewable?->is($card))->toBeTrue()
        ->and($items[1]->copies)->toBe(2);
});

it('faction está en el catálogo con las facciones publicadas como fuentes', function () {
    makeFaction('Imperio');
    makeFaction('Oculta', published: false);

    $registry = app(PdfExportRegistry::class);
    expect($registry->has('faction'))->toBeTrue();

    $sources = $registry->get('faction')->sources('es');
    expect(array_column($sources, 'label'))->toBe(['Imperio']);
});
