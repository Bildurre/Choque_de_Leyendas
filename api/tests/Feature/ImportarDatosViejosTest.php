<?php

// Importación de la web vieja (cdl:importar-viejo) contra los JSON reales de
// database/import: conteos, mapeos (equipo remodelado, flags inferidos),
// deduplicación de habilidades, saneado, avisos, idempotencia y --draft.

use App\Models\AttackRange;
use App\Models\Card;
use App\Models\CardSubtype;
use App\Models\CardType;
use App\Models\EquipmentSubtype;
use App\Models\EquipmentType;
use App\Models\Faction;
use App\Models\Hero;
use App\Models\HeroAbility;
use App\Models\HeroClass;
use App\Models\HeroRace;
use App\Models\HeroSuperclass;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    // Las previews que disparan los created()/updated() no se ejecutan aquí:
    // van a la cola (el usuario las regenerará desde el gestor).
    Queue::fake();
});

it('importa los JSON reales de la web vieja con sus conteos y termina en 0', function () {
    $this->artisan('cdl:importar-viejo')->assertExitCode(0);

    // Taxonomías creadas por nombre
    expect(HeroSuperclass::count())->toBe(3)
        ->and(HeroClass::count())->toBe(12)
        ->and(HeroRace::count())->toBe(3)
        ->and(CardType::count())->toBe(5)
        ->and(CardSubtype::count())->toBe(9)
        ->and(AttackRange::count())->toBe(4);

    // 4 facciones (Mercenarios viene sin héroes ni cartas), 31 héroes,
    // 90 cartas y 51 habilidades únicas repartidas en 62 vínculos.
    expect(Faction::count())->toBe(4)
        ->and(Hero::count())->toBe(31)
        ->and(Hero::whereHas('faction', fn ($q) => $q->where('name->es', 'Defensores de Terik'))->count())->toBe(11)
        ->and(Card::count())->toBe(90)
        ->and(HeroAbility::count())->toBe(51);

    // Por defecto todo publicado (venía de producción)
    expect(Hero::published()->count())->toBe(31)
        ->and(Card::published()->count())->toBe(90)
        ->and(Faction::published()->count())->toBe(4);

    // Facción completa: color de la web vieja (el export no lo trae)
    $mercenarios = Faction::where('name->es', 'Mercenarios')->firstOrFail();
    expect($mercenarios->color)->toBe('#9E9E9E')
        ->and($mercenarios->getTranslation('lore_text', 'es'))->toContain('Mercenarios')
        ->and($mercenarios->heroes()->count())->toBe(0);
});

it('importa un héroe completo con su clase, pasiva saneada y habilidades ordenadas', function () {
    $this->artisan('cdl:importar-viejo')->assertExitCode(0);

    $heroe = Hero::where('name->es', 'Annithare Arain')->firstOrFail();
    expect($heroe->heroRace->getTranslation('name', 'es'))->toBe('Humano')
        ->and($heroe->heroClass->getTranslation('name', 'es'))->toBe('Adalid')
        ->and($heroe->heroClass->heroSuperclass->getTranslation('name', 'es'))->toBe('Devoto')
        ->and($heroe->gender)->toBe('female')
        ->and($heroe->agility)->toBe(3)
        ->and($heroe->armor)->toBe(2)
        ->and($heroe->getTranslation('passive_name', 'es'))->toBe('Luz de Terik')
        // Saneado por lista blanca: las entidades HTML quedan decodificadas
        ->and($heroe->getTranslation('passive_description', 'es'))->toContain('héroe aliado adyacente');

    // Habilidades activas vinculadas en orden y con su tipado y coste canónico
    $habilidades = $heroe->heroAbilities;
    expect($habilidades)->toHaveCount(2)
        ->and($habilidades[0]->getTranslation('name', 'es'))->toBe('Corte Fugaz')
        ->and((int) $habilidades[0]->pivot->position)->toBe(1)
        ->and($habilidades[0]->cost)->toBe('RR')
        ->and($habilidades[0]->attack_type)->toBe('physical')
        ->and($habilidades[0]->attackRange->getTranslation('name', 'es'))->toBe('Melee')
        ->and($habilidades[0]->attackSubtype->getTranslation('name', 'es'))->toBe('Cortante')
        ->and($habilidades[1]->getTranslation('name', 'es'))->toBe('Rayo de Luz')
        ->and($habilidades[1]->cost)->toBe('GB');

    // Deduplicación: «Corte Fugaz» viene en 4 héroes pero es UNA entidad
    expect(HeroAbility::where('name->es', 'Corte Fugaz')->count())->toBe(1)
        ->and(HeroAbility::where('name->es', 'Corte Fugaz')->first()->heroes()->count())->toBe(4);

    // La clase la manda classes.json: pasiva saneada y superclase colgada
    $vanguardia = HeroClass::where('name->es', 'Vanguardia')->firstOrFail();
    expect($vanguardia->heroSuperclass->getTranslation('name', 'es'))->toBe('Combatiente')
        ->and($vanguardia->getTranslation('passive', 'es'))->toContain('Ataque Físico');
});

it('remodela el equipo (categoría → tipo, tipo viejo → subtipo) e infiere los flags de los tipos de carta', function () {
    $this->artisan('cdl:importar-viejo')->assertExitCode(0);

    // Tipos nuevos Arma/Armadura con el flag de manos
    $arma = EquipmentType::where('name->es', 'Arma')->firstOrFail();
    $armadura = EquipmentType::where('name->es', 'Armadura')->firstOrFail();
    expect($arma->uses_hands)->toBeTrue()
        ->and($armadura->uses_hands)->toBeFalse()
        ->and(EquipmentSubtype::count())->toBe(9);

    // El equipment_type viejo «Amuleto» (categoría armor) cuelga de Armadura
    $amuleto = EquipmentSubtype::where('name->es', 'Amuleto')->firstOrFail();
    expect($amuleto->equipment_type_id)->toBe($armadura->id);

    // Carta de equipo con el tipado completo (y sin manos: no es arma)
    $carta = Card::where('name->es', 'Amuleto de Aegis Celeste')->firstOrFail();
    expect($carta->equipment_type_id)->toBe($armadura->id)
        ->and($carta->equipment_subtype_id)->toBe($amuleto->id)
        ->and($carta->hands)->toBeNull()
        ->and($carta->cost)->toBe('RB');

    // Arma con manos
    $arco = Card::where('name->es', 'Arco del Predador de Arena')->firstOrFail();
    expect($arco->equipment_type_id)->toBe($arma->id)
        ->and($arco->hands)->toBe(2);

    // Flags inferidos del uso: Técnica lleva subtipos (y su superclase),
    // Equipo es equipo y Apoyo no lleva nada
    $tecnica = CardType::where('name->es', 'Técnica')->firstOrFail();
    $equipo = CardType::where('name->es', 'Equipo')->firstOrFail();
    $apoyo = CardType::where('name->es', 'Apoyo')->firstOrFail();
    expect($tecnica->allows_subtypes)->toBeTrue()
        ->and($tecnica->is_equipment)->toBeFalse()
        ->and($tecnica->heroSuperclass->getTranslation('name', 'es'))->toBe('Combatiente')
        ->and($equipo->is_equipment)->toBeTrue()
        ->and($apoyo->allows_subtypes)->toBeFalse()
        ->and($apoyo->is_equipment)->toBeFalse();

    // Carta con subtipo: «Luz Sanadora» es Letanía - Rezo con rango sin tipo
    $luz = Card::where('name->es', 'Luz Sanadora')->firstOrFail();
    expect($luz->cardType->getTranslation('name', 'es'))->toBe('Letanía')
        ->and($luz->cardSubtype->getTranslation('name', 'es'))->toBe('Rezo')
        ->and($luz->attackRange->getTranslation('name', 'es'))->toBe('A Distancia')
        ->and($luz->attack_type)->toBeNull();
});

it('avisa (sin bloquear) de la vida que no cuadra, pasivas vacías y armas sin manos', function () {
    $this->artisan('cdl:importar-viejo')
        // La fórmula de vida de la web nueva no reproduce la config vieja
        ->expectsOutputToContain('Héroe «Astheriel Estei»: vida derivada 21 ≠ 19 del export.')
        // classes.json trae dos clases con passive: []
        ->expectsOutputToContain('Clase «Fanático»: pasiva vacía en el export.')
        // Un arma del export viene sin manos
        ->expectsOutputToContain('Carta «Espada de Sangre Antigua»: arma sin manos en el export.')
        ->assertExitCode(0);
});

it('es idempotente: re-ejecutar no duplica nada', function () {
    $this->artisan('cdl:importar-viejo')->assertExitCode(0);
    $this->artisan('cdl:importar-viejo')->assertExitCode(0);

    expect(Faction::count())->toBe(4)
        ->and(Hero::count())->toBe(31)
        ->and(Card::count())->toBe(90)
        ->and(HeroAbility::count())->toBe(51)
        ->and(HeroClass::count())->toBe(12)
        ->and(EquipmentType::count())->toBe(2)
        ->and(EquipmentSubtype::count())->toBe(9);

    // El pivot héroe-habilidad tampoco se duplica
    $heroe = Hero::where('name->es', 'Annithare Arain')->firstOrFail();
    expect($heroe->heroAbilities)->toHaveCount(2);
});

it('respeta los flags de un tipo de carta que ya existe en BD', function () {
    // «Equipo» pre-existente SIN flag de equipo: el import no se lo toca y
    // descarta el tipado de equipo de sus cartas (avisando).
    $tipo = new CardType;
    $tipo->setTranslations('name', ['es' => 'Equipo', 'en' => 'Equipment']);
    $tipo->allows_subtypes = false;
    $tipo->is_equipment = false;
    $tipo->save();

    $this->artisan('cdl:importar-viejo')
        ->expectsOutputToContain('Carta «Amuleto de Aegis Celeste»: el tipo «Equipo» no es de equipo, se descarta el tipado de equipo.')
        ->assertExitCode(0);

    expect($tipo->refresh()->is_equipment)->toBeFalse()
        ->and(CardType::count())->toBe(5)
        ->and(Card::where('name->es', 'Amuleto de Aegis Celeste')->first()->equipment_type_id)->toBeNull();
});

it('con --draft deja todo lo importado en borrador', function () {
    $this->artisan('cdl:importar-viejo', ['--draft' => true])->assertExitCode(0);

    expect(Faction::published()->count())->toBe(0)
        ->and(Hero::published()->count())->toBe(0)
        ->and(Card::published()->count())->toBe(0);
});
