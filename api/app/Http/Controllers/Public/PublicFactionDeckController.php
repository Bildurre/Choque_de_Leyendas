<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Concerns\SortsIndex;
use App\Http\Controllers\Controller;
use App\Http\Resources\Public\PublicFactionDeckItemResource;
use App\Http\Resources\Public\PublicFactionDeckResource;
use App\Models\Faction;
use App\Models\FactionDeck;
use App\Models\GameMode;
use Illuminate\Http\Request;

/**
 * Web pública: mazos de facción. Solo lectura, sin auth, SOLO publicado
 * (también el contenido de cada mazo se limita a lo publicado); localizado
 * por SetLocale. El front agrupa el índice por modo de juego.
 */
class PublicFactionDeckController extends Controller
{
    use SortsIndex;

    /**
     * Índice: tarjetas de mazo con modo, facciones (color) y totales.
     * Filtros: search (multi-campo vía scopeFilter del motor: LIKE sobre
     * el json de cada columna de $searchable — nombre y descripción; la
     * cita queda fuera — en cualquier locale), game_mode_id y faction_id
     * (mazos que incluyan
     * esa facción, pivot faction_deck_faction).
     */
    public function index(Request $request)
    {
        $locale = app()->getLocale();
        $sort = $request->query('sort');

        $query = FactionDeck::published()
            // Búsqueda multi-campo del motor (published ya lo aplica el scope propio)
            ->filter($request->only('search'))
            ->with(['gameMode', 'factions' => fn ($q) => $q->published()])
            ->withSum(['cards as total_cards' => fn ($q) => $q->published()], 'card_faction_deck.copies')
            ->withSum(['heroes as total_heroes' => fn ($q) => $q->published()], 'faction_deck_hero.copies');

        if (($modeId = (int) $request->query('game_mode_id')) > 0) {
            $query->where('game_mode_id', $modeId);
        }

        if (($factionId = (int) $request->query('faction_id')) > 0) {
            $query->whereHas('factions', fn ($q) => $q->where('factions.id', $factionId));
        }

        // Contrato de `sort` (name|name_desc|latest|oldest); sin él (o con un
        // valor desconocido) se conserva el orden histórico: nombre asc del locale.
        if (in_array($sort, self::SORTS, true)) {
            $this->applySort($query, $sort);
        } else {
            $query->orderBy("name->{$locale}");
        }

        return PublicFactionDeckItemResource::collection($query->get());
    }

    /**
     * Opciones de los selects de filtro del índice: modos de juego y
     * facciones publicadas (con color), con nombres YA localizados al
     * locale de la petición.
     */
    public function filters()
    {
        $locale = app()->getLocale();

        return response()->json([
            'modes' => GameMode::query()
                ->orderBy("name->{$locale}")
                ->get()
                ->map(fn (GameMode $mode) => [
                    'id' => $mode->id,
                    'name' => $mode->getTranslation('name', $locale),
                ])
                ->values(),
            'factions' => Faction::published()
                ->orderBy("name->{$locale}")
                ->get()
                ->map(fn (Faction $faction) => [
                    'id' => $faction->id,
                    'name' => $faction->getTranslation('name', $locale),
                    'color' => $faction->color,
                ])
                ->values(),
        ]);
    }

    /** Ficha por slug (vale en cualquier locale) con listas y estadísticas. */
    public function show(string $slug)
    {
        $locale = app()->getLocale();

        $deck = FactionDeck::published()
            ->whereSlug($slug)
            ->firstOrFail();

        $deck->load([
            'gameMode',
            'factions' => fn ($query) => $query->published(),
            'heroes' => fn ($query) => $query->published()
                ->with('heroClass.heroSuperclass')
                ->orderBy("name->{$locale}"),
            'cards' => fn ($query) => $query->published()->with('cardType'),
        ]);

        return new PublicFactionDeckResource($deck);
    }
}
