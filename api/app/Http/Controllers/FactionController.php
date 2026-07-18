<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SanitizesRichText;
use App\Http\Controllers\Concerns\SortsIndex;
use App\Http\Resources\FactionResource;
use App\Models\Card;
use App\Models\CardType;
use App\Models\Faction;
use App\Models\GameMode;
use App\Models\HeroClass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/** CRUD de admin para Faction (facción; por slug traducible). */
class FactionController extends Controller
{
    use SanitizesRichText;
    use SortsIndex;

    public function index(Request $request)
    {
        $factions = Faction::query()
            // Cantidades para el panel derecho (héroes, cartas y mazos).
            ->withCount(['heroes', 'cards', 'factionDecks'])
            ->filter($request->only('search', 'status'))
            ->tap(fn ($query) => $this->applySort($query, $request->query('sort')))
            ->paginate(15);

        return FactionResource::collection($factions);
    }

    /** Lista ligera (id + nombre traducible + color) para selectores. */
    public function options()
    {
        return response()->json([
            'data' => Faction::orderByDesc('id')->get()->map(fn (Faction $faction) => [
                'id' => $faction->id,
                'name' => $faction->getTranslations('name'),
                'color' => $faction->color,
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $faction = new Faction;
        $this->fill($faction, $data);
        $faction->save();
        $faction->setImageFromRequest($request);
        // El icono (MediaLibrary) no es columna: no dispara la invalidación
        // declarativa. Se regenera a mano.
        $faction->regeneratePreviews();

        return (new FactionResource($faction))->response()->setStatusCode(201);
    }

    public function show(string $slug)
    {
        $faction = Faction::withCount(['heroes', 'cards', 'factionDecks'])
            ->whereSlug($slug)
            ->firstOrFail();

        return new FactionResource($faction);
    }

    /**
     * Estadísticas del single de facción (como DashboardStatsController pero
     * acotadas a la facción): cartas (total, por tipo, curva de coste),
     * héroes (total, por clase y por superclase) y mazos (total, por modo).
     * Agregados en BBDD y nombres localizados al locale de la petición.
     */
    public function stats(string $slug)
    {
        $faction = Faction::whereSlug($slug)->firstOrFail();
        $locale = app()->getLocale();

        // Cartas por tipo (solo tipos con cartas de la facción).
        $cardTypeCounts = $faction->cards()
            ->selectRaw('card_type_id, count(*) as total')
            ->groupBy('card_type_id')
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

        // Curva de coste por nº de dados: cost canónico ⇒ length = dados.
        $byDice = $faction->cards()
            ->selectRaw('coalesce(length(cost), 0) as dice, count(*) as total')
            ->groupBy('dice')
            ->pluck('total', 'dice');

        $costCurve = [];
        for ($dice = 0; $dice <= Card::COST_MAX; $dice++) {
            $costCurve[] = ['dice' => $dice, 'count' => (int) ($byDice[$dice] ?? 0)];
        }

        // Héroes por clase (y su superclase, agregada después en PHP).
        $classCounts = $faction->heroes()
            ->selectRaw('hero_class_id, count(*) as total')
            ->groupBy('hero_class_id')
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

        // Mazos que incluyen la facción, por modo de juego.
        $modeCounts = $faction->factionDecks()
            ->selectRaw('game_mode_id, count(*) as total')
            ->groupBy('game_mode_id')
            ->pluck('total', 'game_mode_id');

        $decksByMode = GameMode::whereIn('id', $modeCounts->keys())->get()
            ->map(fn (GameMode $mode) => [
                'id' => $mode->id,
                'name' => $mode->getTranslation('name', $locale),
                'count' => (int) $modeCounts[$mode->id],
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
                'decks' => [
                    'total' => (int) $modeCounts->sum(),
                    'by_game_mode' => $decksByMode,
                ],
            ],
        ]);
    }

    public function update(Request $request, string $slug)
    {
        $faction = Faction::whereSlug($slug)->firstOrFail();
        $data = $this->validateData($request);
        $this->fill($faction, $data);
        $faction->save();
        $faction->setImageFromRequest($request);
        // El icono (MediaLibrary) no es columna: no dispara la invalidación
        // declarativa. Se regenera a mano.
        $faction->regeneratePreviews();

        return new FactionResource($faction);
    }

    public function destroy(string $slug)
    {
        Faction::whereSlug($slug)->firstOrFail()->delete();

        return response()->noContent();
    }

    public function restore(int $id)
    {
        $faction = Faction::withTrashed()->findOrFail($id);
        $faction->restore();

        return new FactionResource($faction);
    }

    /** Borrado definitivo (desde la papelera): elimina la fila y su icono. */
    public function forceDestroy(int $id)
    {
        $faction = Faction::withTrashed()->findOrFail($id);
        $faction->clearMediaCollection('image');
        $faction->forceDelete();

        return response()->noContent();
    }

    public function togglePublished(string $slug)
    {
        $faction = Faction::whereSlug($slug)->firstOrFail();
        $faction->togglePublished();

        return new FactionResource($faction);
    }

    /** Valida los campos traducibles por locale + color hex + icono opcional. */
    protected function validateData(Request $request): array
    {
        $default = config('motor.default_locale');
        $rules = [
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_published' => ['boolean'],
            'image' => ['nullable', 'image', 'max:4096'],
        ];
        foreach (array_keys(config('motor.locales', [])) as $locale) {
            $rules["name.$locale"] = [$locale === $default ? 'required' : 'nullable', 'string', 'max:255'];
            $rules["lore_text.$locale"] = ['nullable', 'string'];
            $rules["epic_quote.$locale"] = ['nullable', 'string'];
        }

        return Validator::make($request->all(), $rules)->validate();
    }

    protected function fill(Faction $faction, array $data): void
    {
        $faction->replaceTranslations('name', array_filter($data['name'] ?? [], fn ($v) => $v !== null && $v !== ''));
        $faction->replaceTranslations('lore_text', $this->cleanRich(array_filter($data['lore_text'] ?? [], fn ($v) => $v !== null && $v !== '')));
        $faction->replaceTranslations('epic_quote', $this->cleanRich(array_filter($data['epic_quote'] ?? [], fn ($v) => $v !== null && $v !== '')));
        $faction->color = $data['color'];
        if (array_key_exists('is_published', $data)) {
            $faction->is_published = (bool) $data['is_published'];
        }
        // text_is_dark lo calcula el modelo en saving().
    }
}
