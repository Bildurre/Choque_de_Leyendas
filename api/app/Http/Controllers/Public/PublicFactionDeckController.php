<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Concerns\SortsIndex;
use App\Http\Controllers\Controller;
use App\Http\Resources\Public\PublicFactionDeckItemResource;
use App\Http\Resources\Public\PublicFactionDeckResource;
use App\Models\FactionDeck;
use Illuminate\Http\Request;

/**
 * Web pública: mazos de facción. Solo lectura, sin auth, SOLO publicado
 * (también el contenido de cada mazo se limita a lo publicado); localizado
 * por SetLocale. El front agrupa el índice por modo de juego.
 */
class PublicFactionDeckController extends Controller
{
    use SortsIndex;

    /** Índice: tarjetas de mazo con modo, facciones (color) y totales. */
    public function index(Request $request)
    {
        $locale = app()->getLocale();
        $sort = $request->query('sort');

        $query = FactionDeck::published()
            ->with(['gameMode', 'factions' => fn ($q) => $q->published()])
            ->withSum(['cards as total_cards' => fn ($q) => $q->published()], 'card_faction_deck.copies')
            ->withCount(['heroes as total_heroes' => fn ($q) => $q->published()]);

        // Contrato de `sort` (name|name_desc|latest); sin él (o con un valor
        // desconocido) se conserva el orden histórico: nombre asc del locale.
        if (in_array($sort, ['name', 'name_desc', 'latest'], true)) {
            $this->applySort($query, $sort);
        } else {
            $query->orderBy("name->{$locale}");
        }

        return PublicFactionDeckItemResource::collection($query->get());
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
