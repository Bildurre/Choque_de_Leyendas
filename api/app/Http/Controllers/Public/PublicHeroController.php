<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Concerns\SortsIndex;
use App\Http\Controllers\Controller;
use App\Http\Resources\Public\PublicHeroResource;
use App\Models\Faction;
use App\Models\Hero;
use App\Models\HeroClass;
use App\Models\HeroRace;
use App\Models\HeroSuperclass;
use Edc\Core\Previews\CatalogItem;
use Illuminate\Http\Request;

/**
 * Web pública: índice de héroes con filtros de juego + single de héroe.
 * El índice extiende al catálogo genérico del motor (/api/catalog/hero):
 * misma forma de ítem y de paginación, más facción, clase, superclase y
 * raza. Solo lectura, sin auth, SOLO publicado; localizado por SetLocale.
 */
class PublicHeroController extends Controller
{
    use SortsIndex;

    /**
     * Índice filtrable. Parámetros: page, per_page (24, tope 48), search
     * (multi-campo vía scopeFilter del motor: LIKE sobre el json de cada
     * columna de $searchable — nombre y pasiva; lore y cita fuera — en cualquier
     * locale), faction_id, hero_class_id, hero_superclass_id
     * (héroes cuya clase pertenece a esa superclase), hero_race_id y sort
     * (name|name_desc|latest|oldest; por defecto id desc). Ítems
     * {id, name, slug, preview}.
     */
    public function index(Request $request)
    {
        $locale = app()->getLocale();
        // Búsqueda multi-campo del motor (published ya lo aplica el scope propio)
        $query = Hero::published()->filter($request->only('search'));

        foreach (['faction_id', 'hero_class_id', 'hero_race_id'] as $column) {
            if (($id = (int) $request->query($column)) > 0) {
                $query->where($column, $id);
            }
        }

        // La superclase llega a través de la clase (el héroe no la guarda).
        if (($superclassId = (int) $request->query('hero_superclass_id')) > 0) {
            $query->whereHas(
                'heroClass',
                fn ($q) => $q->where('hero_superclass_id', $superclassId),
            );
        }

        $perPage = min(max((int) $request->query('per_page', 24), 1), 48);
        $paginated = $this->applySort($query, $request->query('sort'))->paginate($perPage);

        return response()->json([
            'data' => $paginated->getCollection()
                ->map(fn (Hero $hero) => CatalogItem::fromModel($hero, 'hero', $locale))
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
     * Opciones de los selects de filtro del índice: facciones publicadas
     * (con color), clases (con su superclase para filtrar en cascada),
     * superclases y razas, con nombres YA localizados al locale de la
     * petición.
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
                    'color' => $faction->color,
                ])
                ->values(),
            'classes' => HeroClass::query()
                ->orderBy("name->{$locale}")
                ->get()
                ->map(fn (HeroClass $class) => [
                    'id' => $class->id,
                    'name' => $class->getTranslation('name', $locale),
                    'superclass_id' => $class->hero_superclass_id,
                ])
                ->values(),
            'superclasses' => $taxonomy(HeroSuperclass::class),
            'races' => $taxonomy(HeroRace::class),
        ]);
    }

    /** Ficha por slug (vale en cualquier locale); 404 si no está publicado. */
    public function show(string $slug)
    {
        $hero = Hero::published()
            ->whereSlug($slug)
            ->with([
                'faction',
                'heroRace',
                'heroClass.heroSuperclass',
                'heroAbilities.attackRange',
                'heroAbilities.attackSubtype',
            ])
            ->firstOrFail();

        return new PublicHeroResource($hero);
    }
}
