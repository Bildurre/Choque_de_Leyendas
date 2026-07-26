<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Concerns\FiltersByIds;
use App\Http\Controllers\Concerns\SortsIndex;
use App\Http\Controllers\Controller;
use App\Http\Resources\Public\PublicCardResource;
use App\Models\AttackRange;
use App\Models\AttackSubtype;
use App\Models\Card;
use App\Models\CardSubtype;
use App\Models\CardType;
use App\Models\EquipmentSubtype;
use App\Models\EquipmentType;
use App\Models\Faction;
use App\Support\GameIcons;
use Edc\Core\Previews\CatalogItem;
use Illuminate\Http\Request;

/**
 * Web pública: índice de cartas con filtros de juego + single de carta.
 * El índice extiende al catálogo genérico del motor (/api/catalog/card):
 * misma forma de ítem y de paginación, más facción, tipo y coste.
 * Solo lectura, sin auth, SOLO publicado; localizado por SetLocale.
 */
class PublicCardController extends Controller
{
    use FiltersByIds;
    use SortsIndex;

    /**
     * Índice filtrable. Parámetros: page, per_page (24, tope 48), search
     * (multi-campo vía scopeFilter del motor: LIKE sobre el json de cada
     * columna de $searchable — nombre, efecto y restricción; lore y cita
     * fuera — en el locale activo), faction_id, card_type_id, card_subtype_id,
     * equipment_type_id, equipment_subtype_id, attack_range_id,
     * attack_subtype_id, attack_type
     * (physical|magical), area ('1'/'0'; ausente = no filtra), cost_total
     * (0..5; 0 = cartas sin coste), cost_colors (subconjunto de "RGB": la
     * carta debe contener al menos esos dados) y sort (name|name_desc|
     * latest|oldest; por defecto nombre asc del locale activo). Los filtros
     * de id y de enum aceptan escalar o array (whereIn, contrato de
     * FiltersByIds). Ítems {id, name, slug, preview}.
     */
    public function index(Request $request)
    {
        $locale = app()->getLocale();
        // Búsqueda multi-campo del motor (published ya lo aplica el scope propio)
        $query = Card::published()->filter($request->only('search'));

        // Filtros por columna directa (ids de taxonomías del juego),
        // multivalor: escalar o array → whereIn.
        foreach (['faction_id', 'card_type_id', 'card_subtype_id', 'equipment_type_id', 'equipment_subtype_id', 'attack_range_id', 'attack_subtype_id'] as $column) {
            if (($ids = $this->idsFrom($request, $column)) !== []) {
                $query->whereIn($column, $ids);
            }
        }

        $attackTypes = $this->valuesFrom($request, 'attack_type', Card::ATTACK_TYPES);
        if ($attackTypes !== []) {
            $query->whereIn('attack_type', $attackTypes);
        }

        // area llega como '1'/'0'; ausente (o cualquier otra cosa) no filtra
        // (ambos marcados = no acota nada).
        $areas = $this->valuesFrom($request, 'area', ['1', '0']);
        if ($areas !== []) {
            $query->whereIn('area', array_map(fn (string $area) => $area === '1', $areas));
        }

        // 0 también vale: cartas sin coste (cost NULL) — por eso el multi es
        // un OR de scopes costTotal y no un whereIn sobre length(cost).
        $costTotals = array_values(array_map('intval', array_filter(
            $this->valuesFrom($request, 'cost_total'),
            fn (string $total) => ctype_digit($total) && (int) $total <= Card::COST_MAX,
        )));
        if ($costTotals !== []) {
            $query->where(function ($group) use ($costTotals) {
                foreach ($costTotals as $total) {
                    $group->orWhere(fn ($sub) => $sub->costTotal($total));
                }
            });
        }

        if (($colors = (string) $request->query('cost_colors')) !== '') {
            $query->costContainsColors($colors);
        }

        $perPage = min(max((int) $request->query('per_page', 24), 1), 48);
        $paginated = $this->applySort($query, $request->query('sort'))->paginate($perPage);

        return response()->json([
            'data' => $paginated->getCollection()
                ->map(fn (Card $card) => CatalogItem::fromModel($card, 'card', $locale))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * Opciones de los selects de filtro del índice: facciones publicadas,
     * tipos (con sus flags, que deciden qué filtros aplican), subtipos,
     * tipos de equipo, rangos y subtipos de ataque, con nombres YA
     * localizados al locale de la petición. `icons` trae las urls de los
     * dados del gestor de Iconos (null si no están subidos) para pintar el
     * filtro de colores de coste.
     */
    public function filters()
    {
        $locale = app()->getLocale();

        $taxonomy = fn (string $modelClass) => $modelClass::query()
            ->orderBy("name->{$locale}")
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->getTranslation('name', $locale),
            ])
            ->values();

        return response()->json([
            'factions' => Faction::published()
                ->orderBy("name->{$locale}")
                ->get()
                ->map(fn (Faction $faction) => [
                    'id' => $faction->id,
                    'name' => $faction->getTranslation('name', $locale),
                ])
                ->values(),
            'types' => CardType::query()
                ->orderBy("name->{$locale}")
                ->get()
                ->map(fn (CardType $type) => [
                    'id' => $type->id,
                    'name' => $type->getTranslation('name', $locale),
                    'allows_subtypes' => (bool) $type->allows_subtypes,
                    'is_equipment' => (bool) $type->is_equipment,
                ])
                ->values(),
            'subtypes' => $taxonomy(CardSubtype::class),
            'equipment_types' => $taxonomy(EquipmentType::class),
            // Con el tipo al que pertenecen: el front acota el select de
            // subtipos al tipo de equipo elegido.
            'equipment_subtypes' => EquipmentSubtype::query()
                ->orderBy("name->{$locale}")
                ->get()
                ->map(fn (EquipmentSubtype $subtype) => [
                    'id' => $subtype->id,
                    'name' => $subtype->getTranslation('name', $locale),
                    'equipment_type_id' => $subtype->equipment_type_id,
                ])
                ->values(),
            'attack_ranges' => $taxonomy(AttackRange::class),
            'attack_subtypes' => $taxonomy(AttackSubtype::class),
            // url|null por dado; el front omite (o sustituye) los null.
            'icons' => GameIcons::urls(['dice-red', 'dice-green', 'dice-blue']),
        ]);
    }

    /** Ficha por slug (vale en cualquier locale); 404 si no está publicada. */
    public function show(string $slug)
    {
        $card = Card::published()
            ->whereSlug($slug)
            ->with([
                'faction',
                'cardType.heroSuperclass',
                'cardSubtype',
                'equipmentType',
                'equipmentSubtype',
                'attackRange',
                'attackSubtype',
                'heroAbility.attackRange',
                'heroAbility.attackSubtype',
            ])
            ->firstOrFail();

        return new PublicCardResource($card);
    }
}
