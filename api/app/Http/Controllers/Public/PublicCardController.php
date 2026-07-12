<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Public\PublicCardResource;
use App\Models\Card;
use App\Models\CardType;
use App\Models\Faction;
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
    /**
     * Índice filtrable. Parámetros: page, per_page (24, tope 48), search
     * (nombre del locale), faction_id, card_type_id, cost_total (1..5) y
     * cost_colors (subconjunto de "RGB": la carta debe contener al menos
     * esos dados). Orden id desc; ítems {id, name, slug, preview}.
     */
    public function index(Request $request)
    {
        $locale = app()->getLocale();
        $query = Card::published();

        if (($search = trim((string) $request->query('search'))) !== '') {
            $query->where("name->{$locale}", 'like', "%{$search}%");
        }

        if (($factionId = (int) $request->query('faction_id')) > 0) {
            $query->ofFaction($factionId);
        }

        if (($typeId = (int) $request->query('card_type_id')) > 0) {
            $query->ofType($typeId);
        }

        $costTotal = (int) $request->query('cost_total');
        if ($costTotal >= 1 && $costTotal <= Card::COST_MAX) {
            $query->costTotal($costTotal);
        }

        if (($colors = (string) $request->query('cost_colors')) !== '') {
            $query->costContainsColors($colors);
        }

        $perPage = min(max((int) $request->query('per_page', 24), 1), 48);
        $paginated = $query->orderByDesc('id')->paginate($perPage);

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
     * Opciones de los selects de filtro del índice: facciones publicadas y
     * todos los tipos, con nombres YA localizados al locale de la petición.
     */
    public function filters()
    {
        $locale = app()->getLocale();

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
                ])
                ->values(),
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
                'attackRange',
                'attackSubtype',
                'heroAbility.attackRange',
                'heroAbility.attackSubtype',
            ])
            ->firstOrFail();

        return new PublicCardResource($card);
    }
}
