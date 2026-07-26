<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersByIds;
use App\Http\Controllers\Concerns\SanitizesRichText;
use App\Http\Controllers\Concerns\SortsIndex;
use App\Http\Resources\FactionDeckResource;
use App\Models\Card;
use App\Models\CardType;
use App\Models\FactionDeck;
use App\Models\GameMode;
use App\Models\HeroClass;
use Edc\Core\Support\SqlFold;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * CRUD de admin para los mazos de facción, más los endpoints del editor
 * (cartas con copias; héroes solo asignación, siempre 1 copia). Guardar
 * borradores es libre; al publicar se exige la configuración del modo (422
 * con errores localizables).
 */
class FactionDeckController extends Controller
{
    use FiltersByIds;
    use SanitizesRichText;
    use SortsIndex;

    public function index(Request $request)
    {
        // Filtros multivalor (escalar o array, contrato de FiltersByIds).
        $factionIds = $this->idsFrom($request, 'faction_id');
        $gameModeIds = $this->idsFrom($request, 'game_mode_id');
        $heroIds = $this->idsFrom($request, 'hero_id');
        $cardIds = $this->idsFrom($request, 'card_id');
        $search = trim((string) $request->input('search', ''));

        $decks = FactionDeck::query()
            ->with(['gameMode', 'factions'])
            ->withSum('cards as total_cards', 'card_faction_deck.copies')
            ->withCount('heroes as total_heroes')
            // La búsqueda va aparte (ver applySearch: también por el nombre
            // del contenido); a filter() solo llega el estado de las tabs.
            ->filter($request->only('status'))
            ->when($search !== '', fn ($query) => $this->applySearch($query, $search))
            // Filtro por facción (pivot): lo usan el multiselect del panel y
            // el single de facción ("mazos de esta facción").
            ->when($factionIds !== [], fn ($query) => $query->whereHas(
                'factions',
                fn ($q) => $q->whereIn('factions.id', $factionIds),
            ))
            ->when($gameModeIds !== [], fn ($query) => $query->whereIn('game_mode_id', $gameModeIds))
            // Filtros de contenido: mazos que contengan CUALQUIERA de los
            // héroes/cartas marcados en los multiselects del panel.
            ->when($heroIds !== [], fn ($query) => $query->whereHas(
                'heroes',
                fn ($q) => $q->whereIn('heroes.id', $heroIds),
            ))
            ->when($cardIds !== [], fn ($query) => $query->whereHas(
                'cards',
                fn ($q) => $q->whereIn('cards.id', $cardIds),
            ))
            ->tap(fn ($query) => $this->applySort($query, $request->query('sort')))
            ->paginate(15);

        return FactionDeckResource::collection($decks);
    }

    /**
     * Búsqueda del index: además de las columnas buscables del propio mazo
     * ($searchable del modelo: name y description, traducibles), el término
     * casa por NOMBRE de carta o de héroe contenidos en el mazo. Mismo
     * plegado que HasFilters (SqlFold en columna Y término, en el json del
     * locale activo: "aMuLeTo" encuentra "Amuleto").
     */
    protected function applySearch(Builder $query, string $search): void
    {
        $locale = app()->getLocale();
        $term = '%'.SqlFold::term($search).'%';

        // LIKE plegado sobre el json del locale activo de una columna
        // traducible, con el grammar del builder que la va a ejecutar.
        $fold = fn (Builder $q, string $column): string => SqlFold::expression(
            $q->getQuery()->getGrammar()->wrap("{$column}->{$locale}"),
        ).' like ?';

        // Agrupado para no romper los demás wheres del listado (status, filtros).
        $query->where(function (Builder $group) use ($fold, $term) {
            $group->whereRaw($fold($group, 'name'), [$term])
                ->orWhereRaw($fold($group, 'description'), [$term])
                ->orWhereHas('cards', fn (Builder $q) => $q->whereRaw($fold($q, 'name'), [$term]))
                ->orWhereHas('heroes', fn (Builder $q) => $q->whereRaw($fold($q, 'name'), [$term]));
        });
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $deck = new FactionDeck;
        $this->fill($deck, $data);

        // Un mazo recién creado no puede nacer publicado si no cumple límites.
        if ($deck->is_published && ($errors = $this->publishErrors($deck)) !== []) {
            return $this->publishRejected($errors);
        }

        $deck->save();
        $deck->setImageFromRequest($request);
        $deck->factions()->sync($data['faction_ids']);
        // Icono (MediaLibrary) y facciones (pivot) no son columnas: no
        // disparan la invalidación declarativa. Se regenera a mano.
        $deck->regeneratePreviews();

        return (new FactionDeckResource($deck->load(['gameMode', 'factions'])))
            ->response()->setStatusCode(201);
    }

    /** El mazo con todo cargado (cartas con copias, héroes, facciones). */
    public function show(string $slug)
    {
        // Cartas y héroes en alfabético del locale activo, como el resto de listas.
        $locale = app()->getLocale();

        $deck = FactionDeck::with([
            'gameMode',
            'factions',
            'heroes' => fn ($q) => $q->orderBy("name->{$locale}"),
            'cards' => fn ($q) => $q->orderBy("name->{$locale}"),
        ])->whereSlug($slug)->firstOrFail();

        return new FactionDeckResource($deck);
    }

    /**
     * Estadísticas del single de mazo (como FactionController@stats pero
     * acotadas al contenido del mazo): cartas (total, por tipo y curva de
     * coste, AMBAS ponderadas por copias del pivot) y héroes (total, por
     * clase y por superclase; sin copias: cada héroe cuenta 1). Agregados
     * en BBDD y nombres localizados al locale de la petición.
     */
    public function stats(string $slug)
    {
        $deck = FactionDeck::whereSlug($slug)->firstOrFail();
        $locale = app()->getLocale();

        // Cartas por tipo, sumando las copias (solo tipos con cartas del mazo).
        $cardTypeCounts = $deck->cards()
            ->selectRaw('cards.card_type_id, sum(card_faction_deck.copies) as total')
            ->groupBy('cards.card_type_id')
            ->pluck('total', 'card_type_id');

        $cardsByType = CardType::whereIn('id', $cardTypeCounts->keys())->get()
            ->map(fn (CardType $type) => [
                'id' => $type->id,
                'name' => $type->getTranslation('name', $locale),
                'count' => (int) $cardTypeCounts[$type->id],
            ])
            ->sortByDesc('count')
            ->values()
            ->all();

        // Curva de coste por nº de dados (cost canónico ⇒ length = dados),
        // también en copias: cada copia de la carta cuenta en su columna.
        $byDice = $deck->cards()
            ->selectRaw('coalesce(length(cards.cost), 0) as dice, sum(card_faction_deck.copies) as total')
            ->groupBy('dice')
            ->pluck('total', 'dice');

        $costCurve = [];
        for ($dice = 0; $dice <= Card::COST_MAX; $dice++) {
            $costCurve[] = ['dice' => $dice, 'count' => (int) ($byDice[$dice] ?? 0)];
        }

        // Héroes por clase (y su superclase, agregada después en PHP).
        $classCounts = $deck->heroes()
            ->selectRaw('heroes.hero_class_id, count(*) as total')
            ->groupBy('heroes.hero_class_id')
            ->pluck('total', 'hero_class_id');

        $classes = HeroClass::with('heroSuperclass')
            ->whereIn('id', $classCounts->keys())
            ->get();

        $heroesByClass = $classes
            ->map(fn (HeroClass $class) => [
                'id' => $class->id,
                'name' => $class->getTranslation('name', $locale),
                'count' => (int) $classCounts[$class->id],
            ])
            ->sortByDesc('count')
            ->values()
            ->all();

        $heroesBySuperclass = $classes
            ->filter(fn (HeroClass $class) => $class->heroSuperclass !== null)
            ->groupBy('hero_superclass_id')
            ->map(fn ($group) => [
                'id' => $group->first()->heroSuperclass->id,
                'name' => $group->first()->heroSuperclass->getTranslation('name', $locale),
                'count' => $group->sum(fn (HeroClass $class) => (int) $classCounts[$class->id]),
            ])
            ->sortByDesc('count')
            ->values()
            ->all();

        return response()->json([
            'data' => [
                'cards' => [
                    'total' => (int) $cardTypeCounts->sum(),
                    'by_type' => $cardsByType,
                    'cost_curve' => $costCurve,
                ],
                'heroes' => [
                    'total' => (int) $classCounts->sum(),
                    'by_class' => $heroesByClass,
                    'by_superclass' => $heroesBySuperclass,
                ],
            ],
        ]);
    }

    public function update(Request $request, string $slug)
    {
        $deck = FactionDeck::whereSlug($slug)->firstOrFail();
        $data = $this->validateData($request);
        $this->fill($deck, $data);

        // Publicar (o seguir publicado) exige cumplir los límites del modo.
        if ($deck->is_published && ($errors = $this->publishErrors($deck)) !== []) {
            return $this->publishRejected($errors);
        }

        $deck->save();
        $deck->setImageFromRequest($request);
        $deck->factions()->sync($data['faction_ids']);
        // Icono (MediaLibrary) y facciones (pivot) no son columnas: no
        // disparan la invalidación declarativa. Se regenera a mano.
        $deck->regeneratePreviews();

        return new FactionDeckResource($deck->load(['gameMode', 'factions']));
    }

    /** Reemplaza las cartas del mazo (borrador libre: no valida límites). */
    public function updateCards(Request $request, string $slug)
    {
        $deck = FactionDeck::whereSlug($slug)->firstOrFail();
        $data = Validator::make($request->all(), [
            'items' => ['present', 'array'],
            'items.*.card_id' => ['required', 'integer', 'distinct', 'exists:cards,id'],
            'items.*.copies' => ['required', 'integer', 'between:1,99'],
        ])->validate();

        $deck->cards()->sync(collect($data['items'])->mapWithKeys(
            fn (array $item) => [$item['card_id'] => ['copies' => $item['copies']]],
        ));
        // Las cartas (pivot) salen en la preview y no son columnas: a mano.
        $deck->regeneratePreviews();

        return new FactionDeckResource($deck->load(['gameMode', 'factions', 'heroes', 'cards']));
    }

    /**
     * Reemplaza los héroes del mazo (borrador libre). Sin copias: un héroe
     * asignado cuenta como 1 (decisión de producto, no se controla cantidad).
     */
    public function updateHeroes(Request $request, string $slug)
    {
        $deck = FactionDeck::whereSlug($slug)->firstOrFail();
        $data = Validator::make($request->all(), [
            'items' => ['present', 'array'],
            'items.*.hero_id' => ['required', 'integer', 'distinct', 'exists:heroes,id'],
        ])->validate();

        $deck->heroes()->sync(collect($data['items'])->pluck('hero_id'));
        // Los héroes (pivot) salen en la preview y no son columnas: a mano.
        $deck->regeneratePreviews();

        return new FactionDeckResource($deck->load(['gameMode', 'factions', 'heroes', 'cards']));
    }

    public function destroy(string $slug)
    {
        $deck = FactionDeck::whereSlug($slug)->firstOrFail();
        $deck->delete();

        return response()->noContent();
    }

    public function restore(int $id)
    {
        $deck = FactionDeck::withTrashed()->findOrFail($id);
        $deck->restore();

        return new FactionDeckResource($deck);
    }

    /** Borrado definitivo (desde la papelera): elimina la fila y su icono. */
    public function forceDestroy(int $id)
    {
        $deck = FactionDeck::withTrashed()->findOrFail($id);
        $deck->clearMediaCollection('image');
        $deck->forceDelete();

        return response()->noContent();
    }

    public function togglePublished(string $slug)
    {
        $deck = FactionDeck::whereSlug($slug)->firstOrFail();

        // Despublicar siempre; publicar solo si cumple los límites del modo.
        if (! $deck->is_published && ($errors = $this->publishErrors($deck)) !== []) {
            return $this->publishRejected($errors);
        }

        $deck->togglePublished();

        return new FactionDeckResource($deck->load(['gameMode', 'factions']));
    }

    /**
     * Errores de publicación según la configuración del modo del mazo (con
     * fallback al modo por defecto), como claves i18n del admin + parámetros.
     */
    protected function publishErrors(FactionDeck $deck): array
    {
        $mode = GameMode::forMode($deck->game_mode_id);
        if (! $mode) {
            return [];
        }

        $deck->loadMissing(['cards', 'heroes']);

        return $mode->validateDeck($deck);
    }

    /** 422 con la lista de errores localizables bajo `errors.deck`. */
    protected function publishRejected(array $errors)
    {
        return response()->json([
            'message' => 'El mazo no cumple los límites del modo de juego.',
            'errors' => ['deck' => $errors],
        ], 422);
    }

    /** Valida los campos traducibles por locale + relaciones + icono opcional. */
    protected function validateData(Request $request): array
    {
        $default = config('motor.default_locale');
        $rules = [
            'game_mode_id' => ['required', 'integer', 'exists:game_modes,id'],
            // Un mazo sin facción no tiene sentido: al menos una siempre.
            'faction_ids' => ['required', 'array', 'min:1'],
            'faction_ids.*' => ['integer', 'distinct', 'exists:factions,id'],
            'is_published' => ['boolean'],
            'image' => ['nullable', 'image', 'max:4096'],
            // Quitar la imagen actual, diferido desde el form (viaja al guardar).
            'remove_image' => ['sometimes', 'boolean'],
        ];
        foreach (array_keys(config('motor.locales', [])) as $locale) {
            $rules["name.$locale"] = [$locale === $default ? 'required' : 'nullable', 'string', 'max:255'];
            $rules["description.$locale"] = ['nullable', 'string'];
            $rules["epic_quote.$locale"] = ['nullable', 'string'];
        }

        return Validator::make($request->all(), $rules)->validate();
    }

    protected function fill(FactionDeck $deck, array $data): void
    {
        $deck->replaceTranslations('name', array_filter($data['name'] ?? [], fn ($v) => $v !== null && $v !== ''));
        $deck->replaceTranslations('description', $this->cleanRich(array_filter($data['description'] ?? [], fn ($v) => $v !== null && $v !== '')));
        $deck->replaceTranslations('epic_quote', $this->cleanRich(array_filter($data['epic_quote'] ?? [], fn ($v) => $v !== null && $v !== '')));
        $deck->game_mode_id = $data['game_mode_id'];
        if (array_key_exists('is_published', $data)) {
            $deck->is_published = (bool) $data['is_published'];
        }
    }
}
