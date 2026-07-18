<?php

use App\Models\Counter;
use App\Models\GameMode;
use Edc\Core\Content\BlockTypeRegistry;
use Edc\Core\Content\Models\Block;
use Edc\Core\Content\Models\Page;

// Bloques con-datos del juego: counters-list (beneficios/perjuicios) y
// game-modes (todos los modos con su configuración de mazos).

function makeGameBlock(string $type, array $settings = []): Block
{
    $page = new Page;
    $page->setTranslations('title', ['es' => 'Página de prueba']);
    $page->is_published = true;
    $page->save();

    return Block::create([
        'page_id' => $page->id,
        'type' => $type,
        'order' => 1,
        'settings' => $settings,
        'is_printable' => false,
        'is_indexable' => false,
    ]);
}

function makeCounter(string $type, string $name, bool $published = true): Counter
{
    $counter = new Counter;
    $counter->setTranslations('name', ['es' => $name]);
    $counter->setTranslations('effect', ['es' => "<p>Efecto de {$name}</p>"]);
    $counter->type = $type;
    $counter->is_published = $published;
    $counter->save();

    return $counter;
}

it('registra los dos bloques del juego en el registry', function () {
    $registry = app(BlockTypeRegistry::class);

    expect($registry->has('counters-list'))->toBeTrue()
        ->and($registry->has('game-modes'))->toBeTrue()
        ->and($registry->get('counters-list')->toArray()['category'])->toBe('data');
});

it('counters-list lista solo los publicados del tipo elegido, por nombre', function () {
    makeCounter('boon', 'Bendición');
    makeCounter('boon', 'Amparo');
    makeCounter('boon', 'Oculto', published: false);
    makeCounter('bane', 'Maldición');

    $block = makeGameBlock('counters-list', ['counter_type' => 'boon']);
    $data = app(BlockTypeRegistry::class)->get('counters-list')->resolveData($block, 'es');

    expect($data['counter_type'])->toBe('boon')
        ->and(array_column($data['counters'], 'name'))->toBe(['Amparo', 'Bendición'])
        ->and($data['counters'][0])->toHaveKeys(['id', 'name', 'effect', 'image']);
});

it('counters-list cae a boon si el tipo guardado no es válido', function () {
    makeCounter('boon', 'Bendición');

    $block = makeGameBlock('counters-list', ['counter_type' => 'trampa']);
    $data = app(BlockTypeRegistry::class)->get('counters-list')->resolveData($block, 'es');

    expect($data['counter_type'])->toBe('boon');
});

it('game-modes lista los modos con su configuración y mazos publicados', function () {
    // La configuración vive en el propio modo (fusión de deck_attributes).
    $mode = new GameMode;
    $mode->setTranslations('name', ['es' => 'Arena']);
    $mode->setTranslations('description', ['es' => '<p>Rápido</p>']);
    $mode->min_cards = 20;
    $mode->max_cards = 30;
    $mode->max_copies_per_card = 3;
    $mode->required_heroes = 1;
    $mode->save();

    $block = makeGameBlock('game-modes');
    $data = app(BlockTypeRegistry::class)->get('game-modes')->resolveData($block, 'es');

    $arena = collect($data['modes'])->firstWhere('name', 'Arena');
    expect($arena)->not->toBeNull()
        ->and($arena['config']['min_cards'])->toBe(20)
        ->and($arena['config']['required_heroes'])->toBe(1)
        ->and($arena['decks_count'])->toBe(0)
        ->and($arena['description'])->toContain('Rápido');
});
