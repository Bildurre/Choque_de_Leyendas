<?php

namespace App\Pdf;

use App\Models\Card;
use App\Models\FactionDeck;
use App\Models\Hero;
use Edc\Core\Pdf\PdfExport;
use Edc\Core\Pdf\PrintableItem;
use Illuminate\Database\Eloquent\Model;

/**
 * Colección por mazo: los héroes del mazo (1 copia) y sus cartas con las
 * copias del pivot, recortables en el layout 'card' del motor. Mismo orden
 * que el export del viejo (DeckExportService): héroes primero.
 */
class FactionDeckExport extends PdfExport
{
    public function sourceModel(): ?string
    {
        return FactionDeck::class;
    }

    public function items(?Model $source, string $locale): array
    {
        $heroes = $source->heroes()
            ->published()
            ->orderBy('id')
            ->get()
            ->map(fn (Hero $hero) => PrintableItem::preview($hero));

        $cards = $source->cards()
            ->published()
            ->orderBy('id')
            ->get()
            ->map(fn (Card $card) => PrintableItem::preview($card, copies: (int) ($card->pivot->copies ?? 1)));

        return $heroes->concat($cards)->all();
    }

    /** Los mazos publicados, disponibles en el gestor de PDF del admin. */
    public function sources(string $locale): array
    {
        return FactionDeck::query()
            ->published()
            ->orderBy('id')
            ->get()
            ->map(fn (FactionDeck $deck) => [
                'id' => $deck->id,
                'label' => $deck->getTranslation('name', $locale) ?: "#{$deck->id}",
            ])
            ->all();
    }
}
