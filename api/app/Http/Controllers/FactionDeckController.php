<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SanitizesRichText;
use App\Http\Controllers\Concerns\SortsIndex;
use App\Http\Resources\FactionDeckResource;
use App\Models\FactionDeck;
use App\Models\GameMode;
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
    use SanitizesRichText;
    use SortsIndex;

    public function index(Request $request)
    {
        $decks = FactionDeck::query()
            ->with(['gameMode', 'factions'])
            ->withSum('cards as total_cards', 'card_faction_deck.copies')
            ->withCount('heroes as total_heroes')
            ->filter($request->only('search', 'status'))
            ->tap(fn ($query) => $this->applySort($query, $request->query('sort')))
            ->paginate(15);

        return FactionDeckResource::collection($decks);
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
        $deck = FactionDeck::with([
            'gameMode',
            'factions',
            'heroes' => fn ($q) => $q->orderBy('id'),
            'cards' => fn ($q) => $q->orderBy('id'),
        ])->whereSlug($slug)->firstOrFail();

        return new FactionDeckResource($deck);
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
