<?php

namespace App\Pdf;

use App\Models\Counter;
use Edc\Core\Pdf\PdfExport;
use Edc\Core\Pdf\PrintableItem;
use Illuminate\Database\Eloquent\Model;

/**
 * Contadores recortables: los publicados, en el layout 'counter' que ya trae
 * el motor (25x25 mm, rejilla densa). El viejo imprimía 10 copias de cada
 * uno, beneficios (boon) primero.
 */
class CountersExport extends PdfExport
{
    /** Copias de cada contador en el PDF (como el viejo). */
    protected const COPIES = 10;

    public function sourceModel(): ?string
    {
        return null;
    }

    public function items(?Model $source, string $locale): array
    {
        return Counter::query()
            ->published()
            ->orderByDesc('type') // 'boon' antes que 'bane'
            ->orderBy('id')
            ->get()
            ->map(fn (Counter $counter) => PrintableItem::preview($counter, copies: self::COPIES))
            ->all();
    }

    /** Preset 25x25 que ya trae el motor. */
    public function layout(): string
    {
        return 'counter';
    }

    public function filename(?Model $source, string $locale): string
    {
        return "counters-{$locale}";
    }
}
