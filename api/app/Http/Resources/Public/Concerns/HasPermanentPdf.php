<?php

namespace App\Http\Resources\Public\Concerns;

use Edc\Core\Pdf\Models\GeneratedPdf;

/**
 * Referencia al PDF PERMANENTE generado de la entidad (gestor de PDF del
 * admin, doc 02): si existe uno listo para el export dado, la ficha pública
 * expone {id, url} — la URL es la descarga pública del motor
 * (GET /api/pdfs/{pdf}/download, sin auth para permanentes) — y el single
 * pinta su botón de descarga. Se prefiere el del locale de la petición y, si
 * no lo hay, cualquier otro listo (mejor un PDF en otro idioma que ninguno).
 */
trait HasPermanentPdf
{
    protected function permanentPdf(string $type, string $locale): ?array
    {
        $pdfs = GeneratedPdf::query()
            ->where('type', $type)
            ->where('source_type', $this->resource->getMorphClass())
            ->where('source_id', $this->resource->getKey())
            ->where('is_permanent', true)
            ->where('status', GeneratedPdf::STATUS_READY)
            ->orderBy('locale')
            ->get();

        $pdf = $pdfs->firstWhere('locale', $locale) ?? $pdfs->first();

        return $pdf ? [
            'id' => $pdf->id,
            'url' => url("/api/pdfs/{$pdf->id}/download"),
        ] : null;
    }
}
