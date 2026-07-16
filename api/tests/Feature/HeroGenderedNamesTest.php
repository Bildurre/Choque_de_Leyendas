<?php

// Nombres con género en clases, superclases y razas (HasGenderedName):
// `name_female` OPCIONAL traducible que solo se muestra junto a un héroe
// femenino y si existe traducción en el locale pedido; en cualquier otro
// caso cae al nombre normal. Los listados/filtros siguen usando `name`.

use App\Models\HeroClass;

require_once __DIR__.'/Public/Helpers.php';

beforeEach(function () {
    // Como un navegador: sin ?locale manda Accept-Language (es)
    $this->withHeader('Accept-Language', 'es');
});

// --- Resolución del trait (nameForGender) ---

it('resuelve el femenino solo para género femenino y con traducción en el locale', function () {
    $class = publicHeroClass(['name' => ['es' => 'Pícaro', 'en' => 'Rogue']]);
    // Femenino solo en es: en (sin género gramatical) no se rellena
    $class->setTranslations('name_female', ['es' => 'Pícara']);
    $class->save();

    // Héroe masculino: siempre el nombre normal
    expect($class->nameForGender('male', 'es'))->toBe('Pícaro')
        // Femenino + traducción en el locale: el femenino
        ->and($class->nameForGender('female', 'es'))->toBe('Pícara')
        // Femenino SIN traducción en ese locale: el nombre normal del locale
        // (nunca el femenino de otro idioma)
        ->and($class->nameForGender('female', 'en'))->toBe('Rogue')
        // Sin locale explícito usa el de la app (es en tests)
        ->and($class->nameForGender('female'))->toBe('Pícara');
});

it('sin name_female cae siempre al nombre normal', function () {
    $race = publicHeroRace(['name' => ['es' => 'Elfo', 'en' => 'Elf']]);

    expect($race->nameForGender('female', 'es'))->toBe('Elfo')
        ->and($race->nameForGender('female', 'en'))->toBe('Elf')
        ->and($race->nameForGender('male', 'es'))->toBe('Elfo');
});

// --- renderData (la preview PNG) ---

it('renderData aplica el género del héroe a raza, clase, superclase y pasiva de clase', function () {
    $class = publicHeroClass(['name' => ['es' => 'Guerrero', 'en' => 'Warrior']]);
    $class->setTranslations('name_female', ['es' => 'Guerrera']);
    $class->save();
    $class->heroSuperclass->setTranslations('name_female', ['es' => 'Luchadora']);
    $class->heroSuperclass->save();

    $race = publicHeroRace(['name' => ['es' => 'Humano', 'en' => 'Human']]);
    $race->setTranslations('name_female', ['es' => 'Humana']);
    $race->save();

    $heroina = publicHero(['hero_class_id' => $class->id, 'hero_race_id' => $race->id]);
    $heroina->gender = 'female';
    $heroina->saveQuietly();

    $data = $heroina->fresh()->renderData('es');

    expect($data['race'])->toBe('Humana')
        ->and($data['class'])->toBe('Guerrera')
        ->and($data['superclass'])->toBe('Luchadora')
        // La pasiva de clase se pinta con el nombre de la clase (con género)
        ->and($data['class_passive'])->toBe(['name' => 'Guerrera', 'description' => 'Pasiva de clase']);

    // En inglés no hay femenino relleno: nombres normales
    $dataEn = $heroina->fresh()->renderData('en');
    expect($dataEn['race'])->toBe('Human')
        ->and($dataEn['class'])->toBe('Warrior');
});

it('renderData deja los nombres normales para un héroe masculino', function () {
    $class = publicHeroClass();
    $class->setTranslations('name_female', ['es' => 'Guerrera']);
    $class->save();

    $hero = publicHero(['hero_class_id' => $class->id]);

    $data = $hero->renderData('es');

    expect($data['race'])->toBe('Humano')
        ->and($data['class'])->toBe('Guerrero')
        ->and($data['superclass'])->toBe('Luchador')
        ->and($data['class_passive'])->toBe(['name' => 'Guerrero', 'description' => 'Pasiva de clase']);
});

// --- Ficha pública (single de héroe) ---

it('la ficha pública de una heroína usa los nombres en femenino', function () {
    ensurePublicApiRoutes();

    $class = publicHeroClass();
    $class->setTranslations('name_female', ['es' => 'Guerrera']);
    $class->save();
    $class->heroSuperclass->setTranslations('name_female', ['es' => 'Luchadora']);
    $class->heroSuperclass->save();

    $race = publicHeroRace();
    $race->setTranslations('name_female', ['es' => 'Humana']);
    $race->save();

    $heroina = publicHero(['hero_class_id' => $class->id, 'hero_race_id' => $race->id]);
    $heroina->gender = 'female';
    $heroina->saveQuietly();

    $data = $this->getJson('/api/heroes/aritz')->assertOk()->json('data');

    expect($data['race'])->toBe('Humana')
        ->and($data['class'])->toBe('Guerrera')
        ->and($data['superclass'])->toBe('Luchadora')
        ->and($data['class_passive']['name'])->toBe('Guerrera');
});

it('la ficha pública de un héroe masculino mantiene los nombres normales', function () {
    ensurePublicApiRoutes();

    $class = publicHeroClass();
    $class->setTranslations('name_female', ['es' => 'Guerrera']);
    $class->save();

    publicHero(['hero_class_id' => $class->id]);

    $data = $this->getJson('/api/heroes/aritz')->assertOk()->json('data');

    expect($data['class'])->toBe('Guerrero')
        ->and($data['class_passive']['name'])->toBe('Guerrero');
});

// --- Admin: display con género en contexto de héroe, listados con name ---

it('el admin recibe race_display y class_display resueltos por locale', function () {
    $admin = motorUser('admin');

    $class = publicHeroClass(['name' => ['es' => 'Pícaro', 'en' => 'Rogue']]);
    $class->setTranslations('name_female', ['es' => 'Pícara']);
    $class->save();

    $heroina = publicHero(['hero_class_id' => $class->id]);
    $heroina->gender = 'female';
    $heroina->saveQuietly();

    $data = $this->actingAs($admin)->getJson('/api/admin/heroes/aritz')->assertOk()->json('data');

    // El femenino solo en es (en cae al nombre normal); el mapa para editar
    // (hero_class.name) sigue intacto.
    expect($data['class_display'])->toBe(['es' => 'Pícara', 'en' => 'Rogue'])
        ->and($data['race_display'])->toBe(['es' => 'Humano', 'en' => 'Human'])
        ->and($data['hero_class']['name'])->toBe(['es' => 'Pícaro', 'en' => 'Rogue']);
});

it('el CRUD de clases guarda y devuelve name_female; el listado sigue con name', function () {
    $admin = motorUser('admin');
    $superclass = publicHeroClass()->hero_superclass_id;

    // Alta con femenino solo en es (el form manda todos los locales)
    $created = $this->actingAs($admin)->postJson('/api/admin/hero-classes', [
        'name' => ['es' => 'Brujo', 'en' => 'Warlock'],
        'name_female' => ['es' => 'Bruja', 'en' => ''],
        'hero_superclass_id' => $superclass,
    ])->assertCreated()->json('data');

    expect($created['name_female'])->toBe(['es' => 'Bruja']);

    // El índice del admin expone name (el listado pinta el nombre normal)
    $index = $this->actingAs($admin)->getJson('/api/admin/hero-classes')->assertOk()->json('data');
    $row = collect($index)->firstWhere('id', $created['id']);
    expect($row['name'])->toBe(['es' => 'Brujo', 'en' => 'Warlock'])
        ->and($row['name_female'])->toBe(['es' => 'Bruja']);

    expect(HeroClass::find($created['id'])->nameForGender('female', 'es'))->toBe('Bruja');
});
