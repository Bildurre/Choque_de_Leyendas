<?php

namespace App\Pdf;

use App\Models\Hero;
use Edc\Core\Pdf\PdfExport;
use Edc\Core\Pdf\PrintableItem;
use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo global: todos los héroes publicados, recortables, en el layout
 * 'card' del motor (63x88 mm, 9 por A4). Sin entidad dueña.
 */
class HeroesCatalogExport extends PdfExport
{
    public function sourceModel(): ?string
    {
        return null;
    }

    public function items(?Model $source, string $locale): array
    {
        return Hero::query()
            ->published()
            ->orderBy('id')
            ->get()
            ->map(fn (Hero $hero) => PrintableItem::preview($hero))
            ->all();
    }

    /** El 'card' por defecto del motor, explícito por claridad. */
    public function layout(): string
    {
        return 'card';
    }

    public function filename(?Model $source, string $locale): string
    {
        return "heroes-catalog-{$locale}";
    }
}
