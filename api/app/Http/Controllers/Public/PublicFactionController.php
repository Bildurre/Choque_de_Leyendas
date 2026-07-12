<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Concerns\SortsIndex;
use App\Http\Controllers\Controller;
use App\Http\Resources\Public\PublicFactionItemResource;
use App\Http\Resources\Public\PublicFactionResource;
use App\Models\Faction;
use Illuminate\Http\Request;

/**
 * Web pública: facciones. Solo lectura, sin auth, SOLO publicado; el locale
 * lo fija SetLocale (grupo api) y las respuestas van localizadas a él.
 */
class PublicFactionController extends Controller
{
    use SortsIndex;

    /** Índice: tarjetas de facción con contadores de publicados. */
    public function index(Request $request)
    {
        $locale = app()->getLocale();
        $sort = $request->query('sort');

        $query = Faction::published()
            ->withCount([
                'heroes as heroes_count' => fn ($q) => $q->published(),
                'cards as cards_count' => fn ($q) => $q->published(),
            ]);

        // Contrato de `sort` (name|name_desc|latest|oldest); sin él (o con un
        // valor desconocido) se conserva el orden histórico: nombre asc del locale.
        if (in_array($sort, self::SORTS, true)) {
            $this->applySort($query, $sort);
        } else {
            $query->orderBy("name->{$locale}");
        }

        return PublicFactionItemResource::collection($query->get());
    }

    /** Ficha por slug (vale en cualquier locale) con su contenido publicado. */
    public function show(string $slug)
    {
        $locale = app()->getLocale();

        $faction = Faction::published()
            ->whereSlug($slug)
            ->withCount([
                'heroes as heroes_count' => fn ($query) => $query->published(),
                'cards as cards_count' => fn ($query) => $query->published(),
                'factionDecks as decks_count' => fn ($query) => $query->published(),
            ])
            ->firstOrFail();

        $faction->load([
            'heroes' => fn ($query) => $query->published()->orderBy("name->{$locale}"),
            'cards' => fn ($query) => $query->published()->orderBy("name->{$locale}"),
            'factionDecks' => fn ($query) => $query->published()
                ->with(['gameMode', 'factions' => fn ($q) => $q->published()])
                ->withSum(['cards as total_cards' => fn ($q) => $q->published()], 'card_faction_deck.copies')
                ->withCount(['heroes as total_heroes' => fn ($q) => $q->published()])
                ->orderBy("name->{$locale}"),
        ]);

        return new PublicFactionResource($faction);
    }
}
