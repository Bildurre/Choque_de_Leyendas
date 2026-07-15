<?php

namespace App\Console\Commands;

use App\Models\AttackRange;
use App\Models\AttackSubtype;
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
use Edc\Core\Support\HtmlSanitizer;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Importa a la web nueva los JSON exportados de la web vieja: classes.json
 * (clases con superclase y pasiva) y faction_*.json (facción + héroes con
 * habilidades + cartas). Idempotente: todo se resuelve por el nombre en
 * castellano (name->es); lo que existe se actualiza y lo que no, se crea.
 *
 * Fuera del alcance: las imágenes (se subirán a mano desde el admin) y las
 * previews (el gestor las regenerará; los jobs que disparen los saved()
 * van a la cola normal, aquí no se ejecuta nada).
 */
class ImportarDatosViejos extends Command
{
    protected $signature = 'cdl:importar-viejo
        {--carpeta=database/import : Carpeta con los JSON exportados (relativa a la raíz de la API)}
        {--draft : Dejar todo lo importado en borrador en vez de publicado}';

    protected $description = 'Importa los datos exportados de la web vieja (clases, facciones, héroes y cartas)';

    /**
     * Colores identitarios de la web vieja (database/data/factions.json del
     * repo viejo): el export no los trae y la columna es obligatoria. Solo
     * se aplican al crear; el color de una facción existente se respeta.
     */
    protected const COLORES_FACCION = [
        'Mercenarios' => '#9E9E9E',
        'Defensores de Terik' => '#29b8f0',
        'Guardabosques de Thenân' => '#00695C',
        'Tribu Llama Furiosa' => '#212121',
    ];

    /**
     * Superclase asociada a cada tipo de carta con subtipos (los "ids
     * mágicos" del viejo: Técnica→Combatiente, Hechizo→Conjurador y, por el
     * mismo patrón, Letanía→Devoto). Solo se aplica al crear el tipo.
     */
    protected const SUPERCLASE_POR_TIPO = [
        'Técnica' => 'Combatiente',
        'Hechizo' => 'Conjurador',
        'Letanía' => 'Devoto',
    ];

    /** Contadores por entidad: entidad => [creados, actualizados, sin cambios]. */
    protected array $contadores = [];

    /** Avisos no fatales acumulados (se listan al final). */
    protected array $avisos = [];

    /** Cache por ejecución: clase de modelo => (name->es => modelo resuelto). */
    protected array $resueltos = [];

    /** Tipos de carta creados en esta ejecución, con sus flags inferidos. */
    protected array $tiposCreados = [];

    protected HtmlSanitizer $sanitizer;

    public function handle(HtmlSanitizer $sanitizer): int
    {
        $this->sanitizer = $sanitizer;

        $carpeta = (string) $this->option('carpeta');
        $carpeta = str_starts_with($carpeta, '/') ? $carpeta : base_path($carpeta);

        if (! is_dir($carpeta)) {
            $this->error("No existe la carpeta {$carpeta}.");

            return self::FAILURE;
        }

        $clases = is_file($carpeta.'/classes.json') ? $this->leer($carpeta.'/classes.json') : null;
        $ficherosFaccion = glob($carpeta.'/faction_*.json') ?: [];

        if ($clases === null && $ficherosFaccion === []) {
            $this->error("No hay nada que importar en {$carpeta} (ni classes.json ni faction_*.json).");

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            'Importando la web vieja desde %s%s…',
            $carpeta,
            $this->option('draft') ? ' (todo en borrador)' : ' (todo publicado)',
        ));

        // Orden: clases primero (crean las superclases que luego referencian
        // los tipos de carta) y facciones después. Los flags de los tipos de
        // carta nuevos se infieren del uso en el conjunto COMPLETO de cartas,
        // así que se cargan todos los ficheros antes de importar ninguno.
        if ($clases !== null) {
            $this->importarClases($clases);
        }

        $facciones = array_map(fn (string $fichero) => $this->leer($fichero), $ficherosFaccion);
        $usoTipos = $this->usoDeTipos($facciones);

        foreach ($facciones as $faccion) {
            $this->importarFaccion($faccion, $usoTipos);
        }

        $this->resumen();

        return self::SUCCESS;
    }

    /** Decodifica un JSON del export (fatal si no se puede leer). */
    protected function leer(string $fichero): array
    {
        $datos = json_decode((string) file_get_contents($fichero), true);

        if (! is_array($datos)) {
            throw new RuntimeException("El JSON de {$fichero} no se puede decodificar.");
        }

        return $datos;
    }

    // --- Clases (classes.json) ---

    protected function importarClases(array $clases): void
    {
        foreach ($clases as $datos) {
            $superclase = $this->upsert(HeroSuperclass::class, $datos['superclass'], 'Superclases');

            // La pasiva puede venir como [] (export sin contenido): se avisa.
            $pasiva = $datos['passive'] ?? [];
            if ($this->plano($pasiva) === []) {
                $this->avisos[] = "Clase «{$datos['name']['es']}»: pasiva vacía en el export.";
            }

            $this->upsert(HeroClass::class, $datos['name'], 'Clases', function (HeroClass $clase) use ($superclase, $pasiva) {
                $clase->hero_superclass_id = $superclase->id;
                $clase->replaceTranslations('passive', $this->rico($pasiva));
            });
        }

        $this->line(sprintf(' - classes.json: %d clases procesadas.', count($clases)));
    }

    // --- Facciones (faction_*.json) ---

    protected function importarFaccion(array $datos, array $usoTipos): void
    {
        $faccion = $this->upsert(Faction::class, $datos['name'], 'Facciones', function (Faction $faccion, bool $creada) use ($datos) {
            $faccion->replaceTranslations('lore_text', $this->rico($datos['lore_text'] ?? []));
            $faccion->replaceTranslations('epic_quote', $this->rico($datos['epic_quote'] ?? []));
            $faccion->is_published = ! $this->option('draft');

            if ($creada) {
                // El export no trae el color (obligatorio): el de la web vieja.
                $color = self::COLORES_FACCION[$datos['name']['es']] ?? null;
                if ($color === null) {
                    $color = '#888888';
                    $this->avisos[] = "Facción «{$datos['name']['es']}»: sin color conocido, se usa {$color}.";
                }
                $faccion->color = $color;
            }
        });

        foreach ($datos['heroes'] ?? [] as $heroe) {
            $this->importarHeroe($heroe, $faccion);
        }

        foreach ($datos['cards'] ?? [] as $carta) {
            $this->importarCarta($carta, $faccion, $usoTipos);
        }

        $this->line(sprintf(
            ' - Facción «%s»: %d héroes, %d cartas.',
            $datos['name']['es'],
            count($datos['heroes'] ?? []),
            count($datos['cards'] ?? []),
        ));
    }

    // --- Héroes y sus habilidades activas ---

    protected function importarHeroe(array $datos, Faction $faccion): void
    {
        $raza = $this->upsert(HeroRace::class, $datos['race'], 'Razas');
        $clase = $this->clase($datos['class'], $datos['superclass']);

        $heroe = $this->upsert(Hero::class, $datos['name'], 'Héroes', function (Hero $heroe) use ($datos, $faccion, $raza, $clase) {
            $heroe->replaceTranslations('passive_name', $this->plano($datos['passive_name'] ?? []));
            foreach (['passive_description', 'lore_text', 'epic_quote'] as $campo) {
                $heroe->replaceTranslations($campo, $this->rico($datos[$campo] ?? []));
            }
            $heroe->faction_id = $faccion->id;
            $heroe->hero_race_id = $raza->id;
            $heroe->hero_class_id = $clase->id;
            $heroe->gender = $datos['gender'];
            foreach (['agility', 'mental', 'will', 'strength', 'armor'] as $atributo) {
                $heroe->{$atributo} = (int) $datos['attributes'][$atributo];
            }
            $heroe->is_published = ! $this->option('draft');
        });

        // La vida ya no se guarda (es derivada de los atributos): si no
        // cuadra con la del export es que la fórmula/configuración cambió
        // respecto a la web vieja — se avisa, no se bloquea.
        $exportada = $datos['attributes']['health'] ?? null;
        if ($exportada !== null && $heroe->health !== (int) $exportada) {
            $this->avisos[] = "Héroe «{$datos['name']['es']}»: vida derivada {$heroe->health} ≠ {$exportada} del export.";
        }

        // Habilidades activas: entidad propia deduplicada por nombre (la
        // misma habilidad viene repetida en varios héroes; en el export las
        // repeticiones son idénticas) y pivot con posición 1-based.
        $pivot = [];
        foreach (array_values($datos['abilities'] ?? []) as $indice => $habilidad) {
            $pivot[$this->habilidad($habilidad)->id] = ['position' => $indice + 1];
        }
        $heroe->heroAbilities()->sync($pivot);
    }

    /**
     * Clase referenciada por un héroe: si ya existe (normalmente creada por
     * classes.json, que manda sobre pasiva y superclase) no se toca; si no,
     * se crea con su superclase y sin pasiva, avisando.
     */
    protected function clase(array $nombre, array $superclase): HeroClass
    {
        return $this->upsert(HeroClass::class, $nombre, 'Clases', function (HeroClass $clase, bool $creada) use ($nombre, $superclase) {
            if (! $creada) {
                return;
            }
            $clase->hero_superclass_id = $this->upsert(HeroSuperclass::class, $superclase, 'Superclases')->id;
            $this->avisos[] = "Clase «{$nombre['es']}»: no está en classes.json, creada sin pasiva desde un héroe.";
        });
    }

    protected function habilidad(array $datos): HeroAbility
    {
        return $this->upsert(HeroAbility::class, $datos['name'], 'Habilidades', function (HeroAbility $habilidad) use ($datos) {
            $habilidad->replaceTranslations('description', $this->rico($datos['effect'] ?? []));
            $habilidad->cost = $datos['cost']; // HasCost normaliza (R→G→B)
            $habilidad->attack_type = in_array($datos['attack_type'] ?? null, HeroAbility::ATTACK_TYPES, true)
                ? $datos['attack_type']
                : null;
            $habilidad->attack_range_id = $this->taxonomia(AttackRange::class, $datos['attack_range'] ?? null, 'Rangos de ataque')?->id;
            $habilidad->attack_subtype_id = $this->taxonomia(AttackSubtype::class, $datos['attack_subtype'] ?? null, 'Subtipos de ataque')?->id;
            $habilidad->area = (bool) ($datos['area'] ?? false);
        });
    }

    // --- Cartas ---

    protected function importarCarta(array $datos, Faction $faccion, array $usoTipos): void
    {
        $nombre = $datos['name']['es'];
        $tipo = $this->tipoDeCarta($datos['card_type'], $usoTipos);

        // Coherencia con los flags del tipo, como el CRUD del admin: lo que
        // el tipo no admite se descarta (avisando, que es una importación).
        if (! $tipo->allows_subtypes && ! empty($datos['card_subtype'])) {
            $this->avisos[] = "Carta «{$nombre}»: el tipo «{$datos['card_type']['es']}» no admite subtipos, se descarta «{$datos['card_subtype']['es']}».";
        }
        if (! $tipo->is_equipment && ! empty($datos['equipment_type'])) {
            $this->avisos[] = "Carta «{$nombre}»: el tipo «{$datos['card_type']['es']}» no es de equipo, se descarta el tipado de equipo.";
        }

        [$tipoEquipo, $subtipoEquipo] = $tipo->is_equipment ? $this->equipo($datos) : [null, null];

        // Las manos solo aplican a tipos de equipo que las llevan (armas).
        $manos = $datos['hands'] ?? null;
        if (! $tipoEquipo?->uses_hands) {
            $manos = null;
        } elseif ($manos === null) {
            $this->avisos[] = "Carta «{$nombre}»: arma sin manos en el export.";
        }

        // Campos wysiwyg que deberían traer algo y llegan vacíos ([]): la
        // restricción vacía es lo normal, pero el lore o la cita llaman.
        $vacios = array_filter(['lore_text', 'epic_quote'], fn (string $campo) => ($datos[$campo] ?? null) === []);
        if ($vacios !== []) {
            $this->avisos[] = "Carta «{$nombre}»: campos vacíos en el export (".implode(', ', $vacios).').';
        }

        $this->upsert(Card::class, $datos['name'], 'Cartas', function (Card $carta) use ($datos, $faccion, $tipo, $tipoEquipo, $subtipoEquipo, $manos) {
            foreach (['lore_text', 'epic_quote', 'effect', 'restriction'] as $campo) {
                $carta->replaceTranslations($campo, $this->rico($datos[$campo] ?? []));
            }
            $carta->faction_id = $faccion->id;
            $carta->card_type_id = $tipo->id;
            $carta->card_subtype_id = $tipo->allows_subtypes
                ? $this->taxonomia(CardSubtype::class, $datos['card_subtype'] ?? null, 'Subtipos de carta')?->id
                : null;
            $carta->equipment_type_id = $tipoEquipo?->id;
            $carta->equipment_subtype_id = $subtipoEquipo?->id;
            $carta->attack_type = in_array($datos['attack_type'] ?? null, Card::ATTACK_TYPES, true)
                ? $datos['attack_type']
                : null;
            $carta->attack_range_id = $this->taxonomia(AttackRange::class, $datos['attack_range'] ?? null, 'Rangos de ataque')?->id;
            $carta->attack_subtype_id = $this->taxonomia(AttackSubtype::class, $datos['attack_subtype'] ?? null, 'Subtipos de ataque')?->id;
            $carta->hands = $manos;
            $carta->cost = $datos['cost'] ?? null; // HasCost normaliza (R→G→B)
            $carta->area = (bool) ($datos['area'] ?? false);
            $carta->is_unique = (bool) ($datos['is_unique'] ?? false);
            $carta->is_published = ! $this->option('draft');
        });
    }

    /**
     * Tipo de carta por nombre. Si ya existe NO se le tocan los flags; si
     * hay que crearlo, allows_subtypes/is_equipment se infieren del uso en
     * el export (el modelo nuevo no tiene flag de ataque: el tipado de
     * ataque va en cada carta) y se asocia su superclase si le corresponde.
     */
    protected function tipoDeCarta(array $nombre, array $usoTipos): CardType
    {
        return $this->upsert(CardType::class, $nombre, 'Tipos de carta', function (CardType $tipo, bool $creado) use ($nombre, $usoTipos) {
            if (! $creado) {
                return;
            }

            $uso = $usoTipos[$nombre['es']] ?? ['subtipos' => false, 'equipo' => false];
            $tipo->allows_subtypes = $uso['subtipos'];
            $tipo->is_equipment = $uso['equipo'];

            $superclase = null;
            if ($nombreSuperclase = self::SUPERCLASE_POR_TIPO[$nombre['es']] ?? null) {
                $superclase = HeroSuperclass::query()->where('name->es', $nombreSuperclase)->first();
                // La columna es única por superclase: no se roba la de otro tipo.
                $libre = $superclase && ! CardType::query()->where('hero_superclass_id', $superclase->id)->exists();
                if (! $libre) {
                    $this->avisos[] = "Tipo de carta «{$nombre['es']}»: superclase «{$nombreSuperclase}» no disponible, queda sin asociar.";
                    $superclase = null;
                }
                $tipo->hero_superclass_id = $superclase?->id;
            }

            $this->tiposCreados[] = sprintf(
                '%s (subtipos: %s, equipo: %s, superclase: %s)',
                $nombre['es'],
                $tipo->allows_subtypes ? 'sí' : 'no',
                $tipo->is_equipment ? 'sí' : 'no',
                $superclase?->getTranslation('name', 'es') ?? '—',
            );
        });
    }

    /**
     * Uso de cada tipo de carta (name->es) en el export completo: si alguna
     * de sus cartas lleva subtipo o tipado de equipo. Alimenta la inferencia
     * de flags de los tipos que haya que crear.
     */
    protected function usoDeTipos(array $facciones): array
    {
        $uso = [];

        foreach ($facciones as $faccion) {
            foreach ($faccion['cards'] ?? [] as $carta) {
                $tipo = $carta['card_type']['es'];
                $uso[$tipo] ??= ['subtipos' => false, 'equipo' => false];
                $uso[$tipo]['subtipos'] = $uso[$tipo]['subtipos'] || ! empty($carta['card_subtype']);
                $uso[$tipo]['equipo'] = $uso[$tipo]['equipo'] || ! empty($carta['equipment_type']);
            }
        }

        return $uso;
    }

    /**
     * Tipado de equipo remodelado: la categoría vieja (weapon|armor) es el
     * TIPO nuevo (Arma, con manos / Armadura, sin ellas) y el
     * equipment_type viejo (Amuleto, Espada…) pasa a ser el SUBTIPO.
     */
    protected function equipo(array $datos): array
    {
        $viejo = $datos['equipment_type'] ?? null;
        $categoria = $datos['equipment_category'] ?? null;

        if (empty($viejo)) {
            return [null, null];
        }

        if (! in_array($categoria, ['weapon', 'armor'], true)) {
            $this->avisos[] = "Carta «{$datos['name']['es']}»: equipo «{$viejo['es']}» sin categoría conocida, se descarta.";

            return [null, null];
        }

        $tipo = $this->upsert(
            EquipmentType::class,
            $categoria === 'weapon' ? ['es' => 'Arma', 'en' => 'Weapon'] : ['es' => 'Armadura', 'en' => 'Armor'],
            'Tipos de equipo',
            function (EquipmentType $tipo, bool $creado) use ($categoria) {
                if ($creado) {
                    $tipo->uses_hands = $categoria === 'weapon';
                }
            },
        );

        $subtipo = $this->upsert(EquipmentSubtype::class, $viejo, 'Subtipos de equipo', function (EquipmentSubtype $subtipo) use ($tipo) {
            $subtipo->equipment_type_id = $tipo->id;
        });

        return [$tipo, $subtipo];
    }

    // --- Utilidades ---

    /**
     * Resuelve una entidad por su nombre en castellano (la clave de
     * idempotencia del import): si existe la actualiza con $fill y si no la
     * crea. La cache por ejecución evita re-procesar y recontar repetidos
     * (la misma habilidad o taxonomía aparece en varios héroes/cartas).
     *
     * @param  callable(Model, bool):void|null  $fill  Recibe el modelo y si es nuevo.
     */
    protected function upsert(string $modelo, array $nombre, string $entidad, ?callable $fill = null): Model
    {
        $es = $nombre['es'] ?? '';

        if (isset($this->resueltos[$modelo][$es])) {
            return $this->resueltos[$modelo][$es];
        }

        $instancia = $modelo::query()->where('name->es', $es)->first();
        $creado = $instancia === null;
        $instancia ??= new $modelo;

        $instancia->replaceTranslations('name', $this->plano($nombre));
        if ($fill !== null) {
            $fill($instancia, $creado);
        }

        $clave = $creado ? 'creados' : ($instancia->isDirty() ? 'actualizados' : 'sin cambios');
        $this->contadores[$entidad][$clave] = ($this->contadores[$entidad][$clave] ?? 0) + 1;

        $instancia->save();

        return $this->resueltos[$modelo][$es] = $instancia;
    }

    /** Taxonomía opcional: null si el export no la trae; upsert si sí. */
    protected function taxonomia(string $modelo, ?array $nombre, string $entidad): ?Model
    {
        if (empty($nombre) || empty($nombre['es'])) {
            return null;
        }

        return $this->upsert($modelo, $nombre, $entidad);
    }

    /** Mapa {locale => texto} sin nulos ni vacíos (campos de texto plano). */
    protected function plano(mixed $mapa): array
    {
        return array_filter(
            is_array($mapa) ? $mapa : [],
            fn ($valor) => is_string($valor) && $valor !== '',
        );
    }

    /**
     * Como plano(), pero saneando el HTML por lista blanca (los wysiwyg se
     * guardan igual que si entraran por el admin, ver SanitizesRichText).
     */
    protected function rico(mixed $mapa): array
    {
        return array_map(
            fn (string $html) => (string) $this->sanitizer->clean($html),
            $this->plano($mapa),
        );
    }

    /** Informe final: tipos creados con sus flags, contadores y avisos. */
    protected function resumen(): void
    {
        if ($this->tiposCreados !== []) {
            $this->newLine();
            $this->components->info('Tipos de carta creados (flags inferidos del uso en el export):');
            foreach ($this->tiposCreados as $tipo) {
                $this->line(' - '.$tipo);
            }
        }

        $filas = [];
        foreach ($this->contadores as $entidad => $contador) {
            $filas[] = [
                $entidad,
                $contador['creados'] ?? 0,
                $contador['actualizados'] ?? 0,
                $contador['sin cambios'] ?? 0,
            ];
        }
        $this->table(['Entidad', 'Creados', 'Actualizados', 'Sin cambios'], $filas);

        if ($this->avisos === []) {
            $this->components->info('Importación completada sin avisos.');

            return;
        }

        $this->warn(sprintf('Importación completada con %d avisos:', count($this->avisos)));
        foreach ($this->avisos as $aviso) {
            $this->warn(' - '.$aviso);
        }
    }
}
