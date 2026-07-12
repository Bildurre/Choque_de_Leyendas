<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SanitizesRichText;
use App\Http\Controllers\Concerns\SortsIndex;
use App\Http\Resources\HeroAbilityResource;
use App\Models\HeroAbility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/** CRUD de admin para HeroAbility (habilidad activa, resuelta por id). */
class HeroAbilityController extends Controller
{
    use SanitizesRichText;
    use SortsIndex;

    public function index(Request $request)
    {
        $attackType = $request->query('attack_type');
        $area = $request->query('area');
        $costTotal = $request->query('cost_total');

        $abilities = HeroAbility::query()
            ->with(['attackRange', 'attackSubtype'])
            ->filter($request->only('search', 'status'))
            // Filtros del listado (selects junto a la búsqueda). Los valores
            // desconocidos o ausentes se ignoran (no filtran).
            ->when(
                in_array($attackType, HeroAbility::ATTACK_TYPES, true),
                fn ($query) => $query->where('attack_type', $attackType),
            )
            ->when(
                $request->filled('attack_range_id'),
                fn ($query) => $query->where('attack_range_id', $request->integer('attack_range_id')),
            )
            ->when(
                $request->filled('attack_subtype_id'),
                fn ($query) => $query->where('attack_subtype_id', $request->integer('attack_subtype_id')),
            )
            // area llega como '1'/'0'; ausente = no filtra.
            ->when(
                in_array($area, ['1', '0'], true),
                fn ($query) => $query->where('area', $area === '1'),
            )
            // cost_total = longitud del cost canónico (0..5).
            ->when(
                is_string($costTotal) && ctype_digit($costTotal) && (int) $costTotal <= HeroAbility::COST_MAX,
                fn ($query) => $query->costTotal((int) $costTotal),
            )
            ->tap(fn ($query) => $this->applySort($query, $request->query('sort')))
            ->paginate(15);

        return HeroAbilityResource::collection($abilities);
    }

    /**
     * Lista para los selectores de héroes/cartas, ordenada por nombre.
     * Mantiene id + name (mapa de traducciones) + cost (lo que ya consumen
     * los form modals) y añade los datos de ataque para poder mostrarlos.
     */
    public function options()
    {
        $locale = app()->getLocale();

        return response()->json([
            'data' => HeroAbility::with(['attackRange', 'attackSubtype'])
                ->orderBy("name->{$locale}")
                ->get()
                ->map(fn (HeroAbility $a) => [
                    'id' => $a->id,
                    'name' => $a->getTranslations('name'),
                    'cost' => $a->cost,
                    'attack_type' => $a->attack_type,
                    'area' => (bool) $a->area,
                    'range' => $a->attackRange ? [
                        'id' => $a->attackRange->id,
                        'name' => $a->attackRange->getTranslations('name'),
                    ] : null,
                    'subtype' => $a->attackSubtype ? [
                        'id' => $a->attackSubtype->id,
                        'name' => $a->attackSubtype->getTranslations('name'),
                    ] : null,
                ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $ability = new HeroAbility;
        $this->fill($ability, $data);
        $ability->save();

        return (new HeroAbilityResource($ability->load(['attackRange', 'attackSubtype'])))
            ->response()->setStatusCode(201);
    }

    public function update(Request $request, int $id)
    {
        $ability = HeroAbility::findOrFail($id);
        $data = $this->validateData($request);
        $this->fill($ability, $data);
        $ability->save();

        return new HeroAbilityResource($ability->load(['attackRange', 'attackSubtype']));
    }

    public function destroy(int $id)
    {
        HeroAbility::findOrFail($id)->delete();

        return response()->noContent();
    }

    public function restore(int $id)
    {
        $ability = HeroAbility::withTrashed()->findOrFail($id);
        $ability->restore();

        return new HeroAbilityResource($ability);
    }

    /** Borrado definitivo (desde la papelera). */
    public function forceDestroy(int $id)
    {
        HeroAbility::withTrashed()->findOrFail($id)->forceDelete();

        return response()->noContent();
    }

    /** Valida traducibles por locale + datos de ataque + coste (obligatorio). */
    protected function validateData(Request $request): array
    {
        // costRules() trae 'nullable'; aquí el coste es obligatorio.
        $costRules = array_merge(['required'], array_diff(HeroAbility::costRules(), ['nullable']));

        $default = config('motor.default_locale');
        $rules = [
            'attack_type' => ['nullable', 'string', 'in:physical,magical'],
            'attack_range_id' => ['nullable', 'integer', 'exists:attack_ranges,id'],
            'attack_subtype_id' => ['nullable', 'integer', 'exists:attack_subtypes,id'],
            'area' => ['boolean'],
            'cost' => $costRules,
        ];
        foreach (array_keys(config('motor.locales', [])) as $locale) {
            $rules["name.$locale"] = [$locale === $default ? 'required' : 'nullable', 'string', 'max:255'];
            $rules["description.$locale"] = ['nullable', 'string'];
        }

        return Validator::make($request->all(), $rules)->validate();
    }

    protected function fill(HeroAbility $ability, array $data): void
    {
        $ability->replaceTranslations('name', array_filter($data['name'] ?? [], fn ($v) => $v !== null && $v !== ''));
        $ability->replaceTranslations('description', $this->cleanRich(array_filter($data['description'] ?? [], fn ($v) => $v !== null && $v !== '')));
        $ability->attack_type = $data['attack_type'] ?? null;
        $ability->attack_range_id = $data['attack_range_id'] ?? null;
        $ability->attack_subtype_id = $data['attack_subtype_id'] ?? null;
        $ability->area = (bool) ($data['area'] ?? false);
        $ability->cost = $data['cost'];
    }
}
