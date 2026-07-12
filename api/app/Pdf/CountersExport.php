<?php

namespace App\Pdf;

use App\Models\Counter;
use Edc\Core\Pdf\PdfExport;
use Edc\Core\Pdf\PrintableItem;
use Illuminate\Database\Eloquent\Model;

/**
 * Contadores recortables: los publicados, en el layout 'counter' que ya trae
 * el motor (25x25 mm, rejilla densa). El viejo imprimía 10 copias de cada
 * uno, beneficios (boon) primero, y al final los TOKENS DE VIDA (corazón con
 * el valor dentro): 1 y 2 x15 copias; 5, 10 y 20 x10.
 */
class CountersExport extends PdfExport
{
    /** Copias de cada contador en el PDF (como el viejo). */
    protected const COPIES = 10;

    /** Tokens de vida: valor => copias (como el viejo). */
    protected const HEALTH_TOKENS = [1 => 15, 2 => 15, 5 => 10, 10 => 10, 20 => 10];

    public function sourceModel(): ?string
    {
        return null;
    }

    public function items(?Model $source, string $locale): array
    {
        $counters = Counter::query()
            ->published()
            ->orderByDesc('type') // 'boon' antes que 'bane'
            ->orderBy('id')
            ->get()
            ->map(fn (Counter $counter) => PrintableItem::preview($counter, copies: self::COPIES));

        $health = collect(self::HEALTH_TOKENS)
            ->map(fn (int $copies, int $value) => PrintableItem::image($this->healthTokenSvg($value), $copies))
            ->values();

        return $counters->concat($health)->all();
    }

    /**
     * Token de vida como SVG en data-URI (DomPDF lo rasteriza): círculo
     * blanco con borde, el corazón del viejo y el valor centrado encima.
     */
    protected function healthTokenSvg(int $value): string
    {
        $svg = <<<SVG
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
  <circle cx="12" cy="12" r="11.5" fill="#ffffff" stroke="#000000" stroke-width="0.5"/>
  <path d="M12,21.35l-1.45-1.32C5.4,15.36,2,12.28,2,8.5C2,5.42,4.42,3,7.5,3c1.74,0,3.41,0.81,4.5,2.09C13.09,3.81,14.76,3,16.5,3C19.58,3,22,5.42,22,8.5c0,3.78-3.4,6.86-8.55,11.54L12,21.35z" fill="rgb(255,166,200)" stroke="rgb(185,0,15)" stroke-width="1" transform="translate(0,0.6) scale(0.95) translate(0.6,0)"/>
  <text x="12" y="14.6" text-anchor="middle" font-family="Arial, sans-serif" font-size="8" font-weight="bold" fill="#000000">{$value}</text>
</svg>
SVG;

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
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
