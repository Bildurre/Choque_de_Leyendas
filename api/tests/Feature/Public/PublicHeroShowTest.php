<?php

// GET /api/heroes/{slug} — ficha pública de héroe.

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    ensurePublicApiRoutes();
    // Como un navegador: sin ?locale manda Accept-Language (es)
    $this->withHeader('Accept-Language', 'es');
});

it('muestra el héroe publicado con atributos, pasivas y habilidades', function () {
    $faction = publicFaction();
    $hero = publicHero([
        'faction_id' => $faction->id,
        'hero_race_id' => publicHeroRace(['name' => ['es' => 'Humano', 'en' => 'Human']])->id,
        'hero_class_id' => publicHeroClass()->id,
    ]);
    $hero->setTranslations('passive_name', ['es' => 'Instinto', 'en' => 'Instinct']);
    $hero->setTranslations('passive_description', ['es' => 'Repite un dado.', 'en' => 'Reroll a die.']);
    $hero->save();
    $hero->heroAbilities()->attach(publicAbility()->id, ['position' => 1]);

    $response = $this->getJson('/api/heroes/aritz')->assertOk();
    $data = $response->json('data');

    expect($data['name'])->toMatchArray(['es' => 'Aritz', 'en' => 'Aritz the Bold'])
        ->and($data['slug'])->toMatchArray(['es' => 'aritz', 'en' => 'aritz-the-bold'])
        ->and($data['attributes'])->toBe(['agility' => 3, 'mental' => 2, 'will' => 4, 'strength' => 3, 'armor' => 2])
        ->and($data['health'])->toBe($hero->health)
        ->and($data['race'])->toBe('Humano')
        ->and($data['gender'])->toBe('male')
        ->and($data['class'])->toBe('Guerrero')
        ->and($data['superclass'])->toBe('Luchador')
        ->and($data['class_passive'])->toBe(['name' => 'Guerrero', 'description' => 'Pasiva de clase'])
        ->and($data['passive'])->toBe(['name' => 'Instinto', 'description' => 'Repite un dado.'])
        ->and($data['faction'])->toMatchArray(['name' => 'Alianza', 'slug' => 'alianza', 'color' => '#336699'])
        ->and($data['preview'])->toBeNull()
        ->and($data['lore_text'])->toBe('<p>Nació en el norte.</p>')
        ->and($data['epic_quote'])->toBe('Por la Alianza');

    // Habilidad activa con el coste parseado dado a dado
    expect($data['abilities'])->toHaveCount(1)
        ->and($data['abilities'][0]['name'])->toBe('Golpe certero')
        ->and($data['abilities'][0]['cost'])->toBe('RB')
        ->and($data['abilities'][0]['cost_parsed'])->toBe([
            ['color' => 'red', 'letter' => 'R'],
            ['color' => 'blue', 'letter' => 'B'],
        ]);
});

it('expone la URL de la preview grande cuando el PNG está generado', function () {
    $hero = publicHero();
    $hero->preview_image = ['hero' => ['es' => "previews/hero/{$hero->id}-es.png"]];
    $hero->saveQuietly();

    $response = $this->getJson('/api/heroes/aritz')->assertOk();

    expect($response->json('data.preview'))->toContain("previews/hero/{$hero->id}-es.png");
});

it('la facción sin publicar del héroe llega sin slug (sin enlace muerto)', function () {
    $faction = publicFaction(['is_published' => false]);
    publicHero(['faction_id' => $faction->id]);

    $response = $this->getJson('/api/heroes/aritz')->assertOk();

    expect($response->json('data.faction.name'))->toBe('Alianza')
        ->and($response->json('data.faction.slug'))->toBeNull();
});

it('devuelve 404 para un héroe sin publicar', function () {
    publicHero(['is_published' => false]);

    $this->getJson('/api/heroes/aritz')->assertNotFound();
});

it('devuelve 404 para un slug inexistente', function () {
    $this->getJson('/api/heroes/no-existe')->assertNotFound();
});
