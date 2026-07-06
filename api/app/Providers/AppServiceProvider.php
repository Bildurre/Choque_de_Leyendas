<?php

namespace App\Providers;

use Edc\Core\Support\Facades\Sitemap;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     * Aquí se registra lo específico del juego (guia-como-montar-una-web.md):
     *
     *   Previews::register('carta', Carta::class);          // render a PNG (§5)
     *   Pdfs::layout('card-big', [...]);                    // presets de impresión (§6)
     *   Pdfs::register('cartas', CartasExport::class);      // catálogo de PDF (§6)
     *   Blocks::register(MiBloqueConDatos::class);          // bloques con-datos (§3)
     *   Sitemap::add(fn () => [...]);                       // secciones públicas (§9)
     */
    public function boot(): void
    {
        // Plantillas de página del juego: la clave viaja en el payload público
        // y la SPA elige el layout en su templateRegistry.
        config(['motor.content.templates' => [
            ...config('motor.content.templates', []),
            'landing' => 'Portada (ancho completo)',
        ]]);

        // Webfonts elegibles en Configuración (los woff2 viven en public/fonts
        // y se sirven por /api/site/fonts/{path}; la SPA genera los @font-face).
        config(['motor.site.fonts' => [
            ...config('motor.site.fonts', []),
            ...self::webfonts(),
        ]]);

        // El apartado público de Descargas es indexable.
        Sitemap::add(fn () => [[
            'slugs' => ['es' => 'descargas', 'eu' => 'deskargak', 'en' => 'downloads'],
        ]]);
    }

    /** Catálogo de webfonts (regular + cursiva; variables donde las hay). */
    protected static function webfonts(): array
    {
        $family = fn (string $label, string $stackTail, array $files) => [
            'label' => $label,
            'stack' => "'{$label}', {$stackTail}",
            'files' => array_map(fn ($file) => [
                'src' => $file[0],
                'weight' => $file[1] ?? '400',
                'style' => str_contains(strtolower($file[0]), 'italic') ? 'italic' : 'normal',
            ], $files),
        ];

        return [
            'inter' => $family('Inter', 'system-ui, sans-serif', [
                ['inter/InterVariable.woff2', '100 900'],
                ['inter/InterVariable-Italic.woff2', '100 900'],
            ]),
            'opensans' => $family('Open Sans', 'system-ui, sans-serif', [
                ['opensans/opensans.woff2', '300 800'],
                ['opensans/opensans-italic.woff2', '300 800'],
            ]),
            'montserrat' => $family('Montserrat', 'system-ui, sans-serif', [
                ['montserrat/montserrat.woff2', '100 900'],
                ['montserrat/montserrat-italic.woff2', '100 900'],
            ]),
            'roboto' => $family('Roboto', 'system-ui, sans-serif', [
                ['roboto/roboto-regular-webfont.woff2'],
                ['roboto/roboto-italic-webfont.woff2'],
                ['roboto/roboto-bold-webfont.woff2', '700'],
                ['roboto/roboto-bolditalic-webfont.woff2', '700'],
            ]),
            'ebgaramond' => $family('EB Garamond', 'Georgia, serif', [
                ['ebgaramond/ebgaramond.woff2', '400 800'],
                ['ebgaramond/ebgaramond-italic.woff2', '400 800'],
            ]),
            'lora' => $family('Lora', 'Georgia, serif', [
                ['lora/lora.woff2', '400 700'],
                ['lora/lora-italic.woff2', '400 700'],
            ]),
            'playfairdisplay' => $family('Playfair Display', 'Georgia, serif', [
                ['playfairdisplay/playfairdisplay.woff2', '400 900'],
                ['playfairdisplay/playfairdisplay-italic.woff2', '400 900'],
            ]),
            'imfellenglish' => $family('IM Fell English', 'Georgia, serif', [
                ['imfellenglish/imfellenglish-regular-webfont.woff2'],
                ['imfellenglish/imfellenglish-italic-webfont.woff2'],
            ]),
            'italianno' => $family('Italianno', 'cursive', [
                ['italianno/italianno-regular-webfont.woff2'],
            ]),
            'jetbrainsmono' => $family('JetBrains Mono', 'ui-monospace, monospace', [
                ['jetbrainsmono/JetbrainsMonoVariable.woff2', '100 800'],
                ['jetbrainsmono/JetbrainsMonoVariable-Italic.woff2', '100 800'],
            ]),
        ];
    }
}
