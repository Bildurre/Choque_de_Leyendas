<?php

// GET /api/cards — índice público de cartas con filtros de juego.

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    ensurePublicApiRoutes();
    // Como un navegador: sin ?locale manda Accept-Language (es)
    $this->withHeader('Accept-Language', 'es');
});

it('lista solo cartas publicadas con la forma del catálogo y orden id desc', function () {
    $first = publicCard(['name' => ['es' => 'Alfa', 'en' => 'Alpha']]);
    $second = publicCard(['name' => ['es' => 'Beta', 'en' => 'Beta']]);
    publicCard(['name' => ['es' => 'Borrador', 'en' => 'Draft'], 'is_published' => false]);

    $response = $this->getJson('/api/cards')->assertOk();

    $response->assertJsonCount(2, 'data');
    // Misma forma que /api/catalog/card del motor: {id, name, slug, preview}
    expect($response->json('data.0'))->toBe([
        'id' => $second->id,
        'name' => 'Beta',
        'slug' => 'beta',
        'preview' => null,
    ])
        ->and($response->json('data.1.id'))->toBe($first->id)
        ->and($response->json('meta'))->toMatchArray([
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => 24,
            'total' => 2,
        ]);
});

it('filtra por facción', function () {
    $faction = publicFaction();
    $mine = publicCard(['name' => ['es' => 'De la Alianza', 'en' => 'Alliance one'], 'faction_id' => $faction->id]);
    publicCard(['name' => ['es' => 'Neutral', 'en' => 'Neutral']]);

    $response = $this->getJson("/api/cards?faction_id={$faction->id}")->assertOk();

    $response->assertJsonCount(1, 'data');
    expect($response->json('data.0.id'))->toBe($mine->id);
});

it('filtra por tipo de carta', function () {
    $type = publicCardType(['name' => ['es' => 'Conjuro', 'en' => 'Spell']]);
    $mine = publicCard(['name' => ['es' => 'Bola de fuego', 'en' => 'Fireball'], 'card_type_id' => $type->id]);
    publicCard(['name' => ['es' => 'Otra', 'en' => 'Other']]);

    $response = $this->getJson("/api/cards?card_type_id={$type->id}")->assertOk();

    $response->assertJsonCount(1, 'data');
    expect($response->json('data.0.id'))->toBe($mine->id);
});

it('filtra por cost_total (nº exacto de dados)', function () {
    publicCard(['name' => ['es' => 'Un dado', 'en' => 'One die'], 'cost' => 'R']);
    $two = publicCard(['name' => ['es' => 'Dos dados', 'en' => 'Two dice'], 'cost' => 'RG']);
    publicCard(['name' => ['es' => 'Tres dados', 'en' => 'Three dice'], 'cost' => 'RRG']);

    $response = $this->getJson('/api/cards?cost_total=2')->assertOk();

    $response->assertJsonCount(1, 'data');
    expect($response->json('data.0.id'))->toBe($two->id);
});

it('filtra por cost_colors: al menos un dado de cada color pedido', function () {
    $rg = publicCard(['name' => ['es' => 'Roja y verde', 'en' => 'Red green'], 'cost' => 'RG']);
    publicCard(['name' => ['es' => 'Roja y azul', 'en' => 'Red blue'], 'cost' => 'RRB']);
    publicCard(['name' => ['es' => 'Verde y azul', 'en' => 'Green blue'], 'cost' => 'GB']);
    $rgb = publicCard(['name' => ['es' => 'Tricolor', 'en' => 'Tricolor'], 'cost' => 'RGB']);

    // Contiene al menos R y G (los dados extra no molestan)
    $response = $this->getJson('/api/cards?cost_colors=RG')->assertOk();

    $response->assertJsonCount(2, 'data');
    expect(collect($response->json('data'))->pluck('id')->all())
        ->toBe([$rgb->id, $rg->id]); // orden id desc
});

it('normaliza cost_colors: minúsculas y orden libre valen igual', function () {
    $rg = publicCard(['name' => ['es' => 'Roja y verde', 'en' => 'Red green'], 'cost' => 'RG']);
    publicCard(['name' => ['es' => 'Solo roja', 'en' => 'Red only'], 'cost' => 'RR']);

    // "gr" === "RG": se pasa a mayúsculas y se reordena a canónico R→G→B
    $response = $this->getJson('/api/cards?cost_colors=gr')->assertOk();

    $response->assertJsonCount(1, 'data');
    expect($response->json('data.0.id'))->toBe($rg->id);
});

it('combina filtros (facción + cost_colors + cost_total)', function () {
    $faction = publicFaction();
    $match = publicCard(['name' => ['es' => 'La buena', 'en' => 'The one'], 'faction_id' => $faction->id, 'cost' => 'RGB']);
    publicCard(['name' => ['es' => 'Sin facción', 'en' => 'Factionless'], 'cost' => 'RGB']);
    publicCard(['name' => ['es' => 'Coste corto', 'en' => 'Short cost'], 'faction_id' => $faction->id, 'cost' => 'RG']);

    $response = $this->getJson("/api/cards?faction_id={$faction->id}&cost_colors=RG&cost_total=3")->assertOk();

    $response->assertJsonCount(1, 'data');
    expect($response->json('data.0.id'))->toBe($match->id);
});

it('busca por nombre', function () {
    publicCard(['name' => ['es' => 'Espada corta', 'en' => 'Short sword']]);
    publicCard(['name' => ['es' => 'Escudo', 'en' => 'Shield']]);

    $response = $this->getJson('/api/cards?search=espada')->assertOk();

    $response->assertJsonCount(1, 'data');
    expect($response->json('data.0.name'))->toBe('Espada corta');
});

it('busca multi-campo: casa por el efecto aunque el nombre no coincida', function () {
    $robo = publicCard([
        'name' => ['es' => 'Alfa', 'en' => 'Alpha'],
        'effect' => ['es' => 'Roba una carta al rival.', 'en' => 'Steal a card from the rival.'],
    ]);
    publicCard([
        'name' => ['es' => 'Beta', 'en' => 'Beta'],
        'effect' => ['es' => 'Cura dos puntos.', 'en' => 'Heals two points.'],
    ]);
    // Aunque el efecto case, las no publicadas siguen fuera
    publicCard([
        'name' => ['es' => 'Borrador', 'en' => 'Draft'],
        'effect' => ['es' => 'Roba al rival.', 'en' => 'Steal from the rival.'],
        'is_published' => false,
    ]);

    $response = $this->getJson('/api/cards?search=rival')->assertOk();
    expect(collect($response->json('data'))->pluck('id')->all())->toBe([$robo->id]);

    // Lo que no está en ningún campo buscable no casa
    expect($this->getJson('/api/cards?search=grimorio')->assertOk()->json('data'))->toBeEmpty();
});

it('pagina con per_page y lo limita a 48', function () {
    publicCard(['name' => ['es' => 'Una', 'en' => 'One']]);
    publicCard(['name' => ['es' => 'Otra', 'en' => 'Two']]);

    $paged = $this->getJson('/api/cards?per_page=1')->assertOk();
    expect($paged->json('meta'))->toMatchArray(['per_page' => 1, 'last_page' => 2, 'total' => 2]);

    $capped = $this->getJson('/api/cards?per_page=100')->assertOk();
    expect($capped->json('meta.per_page'))->toBe(48);
});

it('ordena por nombre asc y desc con sort (contrato compartido)', function () {
    $bruma = publicCard(['name' => ['es' => 'Bruma', 'en' => 'Mist']]);
    $alfa = publicCard(['name' => ['es' => 'Alfa', 'en' => 'Alpha']]);
    $cieno = publicCard(['name' => ['es' => 'Cieno', 'en' => 'Silt']]);

    $asc = $this->getJson('/api/cards?sort=name')->assertOk();
    expect(collect($asc->json('data'))->pluck('id')->all())
        ->toBe([$alfa->id, $bruma->id, $cieno->id]);

    $desc = $this->getJson('/api/cards?sort=name_desc')->assertOk();
    expect(collect($desc->json('data'))->pluck('id')->all())
        ->toBe([$cieno->id, $bruma->id, $alfa->id]);

    // `latest` o un valor desconocido caen al orden por defecto: id desc
    foreach (['latest', 'raro'] as $sort) {
        $fallback = $this->getJson("/api/cards?sort={$sort}")->assertOk();
        expect(collect($fallback->json('data'))->pluck('id')->all())
            ->toBe([$cieno->id, $alfa->id, $bruma->id]);
    }
});

it('ordena por id asc con sort=oldest (contrato compartido)', function () {
    $first = publicCard(['name' => ['es' => 'Primera', 'en' => 'First']]);
    $second = publicCard(['name' => ['es' => 'Segunda', 'en' => 'Second']]);

    $response = $this->getJson('/api/cards?sort=oldest')->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())
        ->toBe([$first->id, $second->id]);
});

it('filtra por subtipo de carta y tipo de equipo', function () {
    $subtype = publicCardSubtype();
    $equipment = publicEquipmentType();

    $bestia = publicCard(['name' => ['es' => 'Bestia parda', 'en' => 'Brown beast'], 'card_subtype_id' => $subtype->id]);
    $espada = publicCard(['name' => ['es' => 'Mandoble', 'en' => 'Greatsword'], 'equipment_type_id' => $equipment->id]);
    publicCard(['name' => ['es' => 'Neutra', 'en' => 'Plain']]);

    $bySubtype = $this->getJson("/api/cards?card_subtype_id={$subtype->id}")->assertOk();
    expect(collect($bySubtype->json('data'))->pluck('id')->all())->toBe([$bestia->id]);

    $byEquipment = $this->getJson("/api/cards?equipment_type_id={$equipment->id}")->assertOk();
    expect(collect($byEquipment->json('data'))->pluck('id')->all())->toBe([$espada->id]);
});

it('filtra por rango, tipo y subtipo de ataque', function () {
    $range = publicAttackRange();
    $subtype = publicAttackSubtype();

    $melee = publicCard(['name' => ['es' => 'Tajo', 'en' => 'Slice'], 'attack_range_id' => $range->id]);
    $fisica = publicCard(['name' => ['es' => 'Puñetazo', 'en' => 'Punch'], 'attack_type' => 'physical']);
    $magica = publicCard(['name' => ['es' => 'Rayo', 'en' => 'Bolt'], 'attack_type' => 'magical']);
    $corte = publicCard(['name' => ['es' => 'Cuchillada', 'en' => 'Stab'], 'attack_subtype_id' => $subtype->id]);

    $byRange = $this->getJson("/api/cards?attack_range_id={$range->id}")->assertOk();
    expect(collect($byRange->json('data'))->pluck('id')->all())->toBe([$melee->id]);

    $byType = $this->getJson('/api/cards?attack_type=physical')->assertOk();
    expect(collect($byType->json('data'))->pluck('id')->all())->toBe([$fisica->id]);

    $bySubtype = $this->getJson("/api/cards?attack_subtype_id={$subtype->id}")->assertOk();
    expect(collect($bySubtype->json('data'))->pluck('id')->all())->toBe([$corte->id]);

    // Un attack_type desconocido no filtra
    $unknown = $this->getJson('/api/cards?attack_type=raro')->assertOk();
    expect($unknown->json('data'))->toHaveCount(4)
        ->and($magica->attack_type)->toBe('magical');
});

it('filtra por area con 1/0 y lo ignora si no viene', function () {
    $conArea = publicCard(['name' => ['es' => 'Explosión', 'en' => 'Blast'], 'area' => true]);
    $sinArea = publicCard(['name' => ['es' => 'Dardo', 'en' => 'Dart'], 'area' => false]);

    $si = $this->getJson('/api/cards?area=1')->assertOk();
    expect(collect($si->json('data'))->pluck('id')->all())->toBe([$conArea->id]);

    $no = $this->getJson('/api/cards?area=0')->assertOk();
    expect(collect($no->json('data'))->pluck('id')->all())->toBe([$sinArea->id]);

    // Ausente (o un valor raro): no filtra
    foreach (['/api/cards', '/api/cards?area=raro'] as $url) {
        expect($this->getJson($url)->assertOk()->json('data'))->toHaveCount(2);
    }
});

it('cost_total=0 devuelve las cartas sin coste', function () {
    $gratis = publicCard(['name' => ['es' => 'Gratis', 'en' => 'Free'], 'cost' => null]);
    publicCard(['name' => ['es' => 'Cara', 'en' => 'Pricey'], 'cost' => 'RGB']);

    $response = $this->getJson('/api/cards?cost_total=0')->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())->toBe([$gratis->id]);

    // Y sin cost_total no se filtra nada
    expect($this->getJson('/api/cards')->assertOk()->json('data'))->toHaveCount(2);
});
