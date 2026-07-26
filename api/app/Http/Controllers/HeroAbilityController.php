<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersByIds;
use App\Http\Controllers\Concerns\SanitizesRichText;
use App\Http\Controllers\Concerns\SortsIndex;
use App\Http\Resources\HeroAbilityResource;
use App\Models\HeroAbility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/** CRUD de admin para HeroAbility (habilidad activa, resuelta por id). */
class HeroAbilityController extends Controller
{
    use FiltersByIds;
    use SanitizesRichText;
    use SortsIndex;

    public function index(Request $request)
    {
        // Filtros multivalor (escalar o array, contrato de FiltersByIds).
        // Los valores desconocidos o ausentes se ignoran (no filtran).
        $attackTypes = $this->valuesFrom($request, 'attack_type', HeroAbility::ATTACK_TYPES);
        $rangeIds = $this->idsFrom($request, 'attack_range_id');
        $subtypeIds = $this->idsFrom($request, 'attack_subtype_id');
        // area llega como '1'/'0' (ambos marcados = no acota nada).
        $areas = $this->valuesFrom($request, 'area', ['1', '0']);
        // cost_total = longitud del cost canónico (0..5).
        $costTotals = array_values(array_map('intval', array_filter(
            $this->valuesFrom($request, 'cost_total'),
            fn (string $total) => ctype_digit($total) && (int) $total <= HeroAbility::COST_MAX,
        )));

        $abilities = HeroAbility::query()
            ->with(['attackRange', 'attackSubtype'])
            ->filter($request->only('search', 'status'))
            // Filtros del listado (multiselects junto a la búsqueda).
            ->when($attackTypes !== [], fn ($query) => $query->whereIn('attack_type', $attackTypes))
            ->when($rangeIds !== [], fn ($query) => $query->whereIn('attack_range_id', $rangeIds))
            ->when($subtypeIds !== [], fn ($query) => $query->whereIn('attack_subtype_id', $subtypeIds))
            ->when($areas !== [], fn ($query) => $query->whereIn(
                'area',
                array_map(fn (string $area) => $area === '1', $areas),
            ))
            // Cualquiera de los totales marcados (OR de scopes costTotal,
            // porque 0 significa "sin coste": cost NULL, no length 0).
            ->when($costTotals !== [], fn ($query) => $query->where(function ($group) use ($costTotals) {
                foreach ($costTotals as $total) {
                    $group->orWhere(fn ($sub) => $sub->costTotal($total));
                }
            }))
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
        return response()->json([
            'data' => $this->orderByName(HeroAbility::with(['attackRange', 'attackSubtype']))
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
