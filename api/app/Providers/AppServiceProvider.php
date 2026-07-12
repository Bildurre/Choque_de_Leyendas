<?php

namespace App\Providers;

use App\Blocks\CountersListBlock;
use App\Blocks\GameModesBlock;
use App\Models\Card;
use App\Models\Counter;
use App\Models\Faction;
use App\Models\FactionDeck;
use App\Models\Hero;
use App\Pdf\CardsCatalogExport;
use App\Pdf\CountersExport;
use App\Pdf\FactionDeckExport;
use App\Pdf\HeroesCatalogExport;
use Edc\Core\Support\Facades\Blocks;
use Edc\Core\Support\Facades\Pdfs;
use Edc\Core\Support\Facades\Previews;
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
        // Entidades renderizables a PNG (RENDER-SPEC): la clave es el segmento
        // de /_render/:entity y debe casar con el renderRegistry de la app Vue.
        // No hace falta ningún Pdfs::layout: los presets 'card' (63x88) y
        // 'counter' (25x25) ya los trae el motor.
        Previews::register('card', Card::class);
        Previews::register('hero', Hero::class);
        Previews::register('counter', Counter::class);
        Previews::register('faction', Faction::class);
        Previews::register('faction-deck', FactionDeck::class);

        // Catálogo de PDF del juego (gestor de PDF del admin).
        Pdfs::register('cards-catalog', CardsCatalogExport::class);   // todas las cartas publicadas (card)
        Pdfs::register('heroes-catalog', HeroesCatalogExport::class); // todos los héroes publicados (card)
        Pdfs::register('counters', CountersExport::class);            // contadores recortables, 10 copias (counter)
        Pdfs::register('faction-deck', FactionDeckExport::class);     // un PDF por mazo publicado (card)

        // Bloques con-datos del juego (portados del CdL viejo).
        Blocks::register(CountersListBlock::class);
        Blocks::register(GameModesBlock::class);

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

        // El apartado público de Descargas es indexable (solo locales activos:
        // eu queda listo sin generar URLs muertas mientras esté desactivado).
        Sitemap::add(fn () => [[
            'slugs' => array_intersect_key(
                ['es' => 'descargas', 'eu' => 'deskargak', 'en' => 'downloads'],
                config('motor.locales', []),
            ),
        ]]);

        // Sitemap de las entidades públicas: índice + un detalle por slug de
        // cada colección publicada. Los segmentos por locale DEBEN casar con
        // entitySections de la app (app/src/entities/registry.ts); el helper
        // filtra a los locales activos (motor.locales), así que eu queda
        // listo sin generar URLs muertas mientras esté desactivado.
        Sitemap::add(fn () => self::sitemapEntries(
            Card::published()->get(),
            ['es' => 'cartas', 'eu' => 'kartak', 'en' => 'cards'],
        ));
        Sitemap::add(fn () => self::sitemapEntries(
            Hero::published()->get(),
            ['es' => 'heroes', 'eu' => 'heroiak', 'en' => 'heroes'],
        ));
        Sitemap::add(fn () => self::sitemapEntries(
            Faction::published()->get(),
            ['es' => 'facciones', 'eu' => 'fakzioak', 'en' => 'factions'],
        ));
        Sitemap::add(fn () => self::sitemapEntries(
            FactionDeck::published()->get(),
            ['es' => 'mazos', 'eu' => 'sortak', 'en' => 'decks'],
        ));
    }

    /**
     * URLs del sitemap de una colección publicada: índice + un detalle por slug.
     * Restringe el mapa de secciones a los locales activos (config motor.locales)
     * para que al activar eu no salgan URLs muertas antes de tiempo.
     */
    protected static function sitemapEntries($models, array $sections): array
    {
        $sections = array_intersect_key($sections, config('motor.locales', []));
        $entries = [['slugs' => $sections]];

        foreach ($models as $model) {
            $slugs = collect($model->getTranslations('slug'))
                ->only(array_keys($sections))
                ->map(fn (string $slug, string $locale) => "{$sections[$locale]}/{$slug}")
                ->all();

            if ($slugs === []) {
                continue;
            }

            $entries[] = [
                'slugs' => $slugs,
                'updated_at' => $model->updated_at?->toDateString(),
            ];
        }

        return $entries;
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
