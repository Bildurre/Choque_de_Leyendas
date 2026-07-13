<?php

namespace App\Http\Controllers\Public;

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
    use SortsIndex;

    /**
     * Índice filtrable. Parámetros: page, per_page (24, tope 48), search
     * (multi-campo vía scopeFilter del motor: LIKE sobre el json de cada
     * columna de $searchable — nombre, efecto, restricción, lore y cita —
     * en cualquier locale), faction_id, card_type_id, card_subtype_id,
     * equipment_type_id, equipment_subtype_id, attack_range_id,
     * attack_subtype_id, attack_type
     * (physical|magical), area ('1'/'0'; ausente = no filtra), cost_total
     * (0..5; 0 = cartas sin coste), cost_colors (subconjunto de "RGB": la
     * carta debe contener al menos esos dados) y sort (name|name_desc|
     * latest|oldest; por defecto id desc). Ítems {id, name, slug, preview}.
     */
    public function index(Request $request)
    {
        $locale = app()->getLocale();
        // Búsqueda multi-campo del motor (published ya lo aplica el scope propio)
        $query = Card::published()->filter($request->only('search'));

        if (($factionId = (int) $request->query('faction_id')) > 0) {
            $query->ofFaction($factionId);
        }

        if (($typeId = (int) $request->query('card_type_id')) > 0) {
            $query->ofType($typeId);
        }

        // Filtros por columna directa (ids de taxonomías del juego).
        foreach (['card_subtype_id', 'equipment_type_id', 'equipment_subtype_id', 'attack_range_id', 'attack_subtype_id'] as $column) {
            if (($id = (int) $request->query($column)) > 0) {
                $query->where($column, $id);
            }
        }

        $attackType = $request->query('attack_type');
        if (in_array($attackType, Card::ATTACK_TYPES, true)) {
            $query->where('attack_type', $attackType);
        }

        // area llega como '1'/'0'; ausente (o cualquier otra cosa) no filtra.
        $area = $request->query('area');
        if (in_array($area, ['1', '0'], true)) {
            $query->where('area', $area === '1');
        }

        // 0 también vale: cartas sin coste (cost NULL).
        $costTotal = $request->query('cost_total');
        if (is_string($costTotal) && ctype_digit($costTotal) && (int) $costTotal <= Card::COST_MAX) {
            $query->costTotal((int) $costTotal);
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
