<?php

// GET /api/pdf-collection — la colección "para imprimir" (doc 02) en CdL:
// además de los items, el índice expone `generated` (los PDF temporales
// vigentes del dueño) para que la web conserve el enlace de descarga del
// PDF personalizado tras recargar la página.

use Edc\Core\Pdf\Jobs\GeneratePdfJob;
use Edc\Core\Pdf\Models\GeneratedPdf;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/Public/Helpers.php';

beforeEach(function () {
    Storage::fake('public');
});

it('el índice de la colección lista items y PDFs temporales vigentes del invitado', function () {
    $card = publicCard();
    $headers = ['X-Collection-Token' => 'guest-0123456789abcdef'];

    // Sin token no hay colección; con token, se añade y se lista.
    $this->getJson('/api/pdf-collection')->assertUnauthorized();
    $this->postJson('/api/pdf-collection/items', [
        'entity' => 'card', 'id' => $card->id, 'copies' => 2,
    ], $headers)->assertCreated();

    // Generar encola el PDF (202) y el índice lo saca en `generated` como
    // pending; al quedar listo, con su URL (persistencia tras recargar).
    Queue::fake();
    $pdfId = $this->postJson('/api/pdf-collection/generate', ['locale' => 'es'], $headers)
        ->assertAccepted()
        ->json('data.id');
    Queue::assertPushed(GeneratePdfJob::class);

    $this->getJson('/api/pdf-collection', $headers)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.copies', 2)
        ->assertJsonPath('generated.0.id', $pdfId)
        ->assertJsonPath('generated.0.status', 'pending')
        ->assertJsonPath('generated.0.url', null);

    Storage::disk('public')->put('pdfs/collection/global/prueba.pdf', '%PDF-fake');
    GeneratedPdf::findOrFail($pdfId)->update([
        'status' => GeneratedPdf::STATUS_READY,
        'path' => 'pdfs/collection/global/prueba.pdf',
        'generated_at' => now(),
    ]);

    $response = $this->getJson('/api/pdf-collection', $headers)->assertOk();
    expect($response->json('generated.0.status'))->toBe('ready')
        ->and($response->json('generated.0.url'))->toContain('pdfs/collection/global/prueba.pdf')
        ->and($response->json('generated.0.size'))->toBeGreaterThan(0);

    // Caducado: desaparece de `generated` (otro token nunca lo vio).
    GeneratedPdf::findOrFail($pdfId)->update(['expires_at' => now()->subHour()]);
    $this->getJson('/api/pdf-collection', $headers)->assertJsonCount(0, 'generated');
    $this->getJson('/api/pdf-collection', ['X-Collection-Token' => 'otro-9876543210fedcba'])
        ->assertJsonCount(0, 'generated');
});
