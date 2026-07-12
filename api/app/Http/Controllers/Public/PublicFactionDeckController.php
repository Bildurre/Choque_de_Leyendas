<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Public\PublicFactionDeckItemResource;
use App\Http\Resources\Public\PublicFactionDeckResource;
use App\Models\FactionDeck;

/**
 * Web pública: mazos de facción. Solo lectura, sin auth, SOLO publicado
 * (también el contenido de cada mazo se limita a lo publicado); localizado
 * por SetLocale. El front agrupa el índice por modo de juego.
 */
class PublicFactionDeckController extends Controller
{
    /** Índice: tarjetas de mazo con modo, facciones (color) y totales. */
    public function index()
    {
        $locale = app()->getLocale();

        $decks = FactionDeck::published()
            ->with(['gameMode', 'factions' => fn ($query) => $query->published()])
            ->withSum(['cards as total_cards' => fn ($query) => $query->published()], 'card_faction_deck.copies')
            ->withCount(['heroes as total_heroes' => fn ($query) => $query->published()])
            ->orderBy("name->{$locale}")
            ->get();

        return PublicFactionDeckItemResource::collection($decks);
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
