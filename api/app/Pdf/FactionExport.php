<?php

namespace App\Pdf;

use App\Models\Card;
use App\Models\Faction;
use App\Models\Hero;
use Edc\Core\Pdf\PdfExport;
use Edc\Core\Pdf\PrintableItem;
use Illuminate\Database\Eloquent\Model;

/**
 * Hojas de una facción (portado del FactionExportService viejo): sus héroes
 * publicados (1 copia) y sus cartas publicadas (2 copias de cada una),
 * recortables en el layout 'card' del motor.
 */
class FactionExport extends PdfExport
{
    public function sourceModel(): ?string
    {
        return Faction::class;
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
            ->map(fn (Card $card) => PrintableItem::preview($card, copies: 2));

        return $heroes->concat($cards)->all();
    }

    /** Las facciones publicadas, disponibles en el gestor de PDF del admin. */
    public function sources(string $locale): array
    {
        return Faction::query()
            ->published()
            ->orderBy('id')
            ->get()
            ->map(fn (Faction $faction) => [
                'id' => $faction->id,
                'label' => $faction->getTranslation('name', $locale) ?: "#{$faction->id}",
            ])
            ->all();
    }
}
