<?php

namespace Database\Seeders;

use Edc\Core\Site\Models\Setting;
use Edc\Core\Site\SiteSettings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Configuración inicial de la web: título, logo/favicon generados y fuentes.
 * Todo se cambia después desde el admin (sección Configuración).
 * Idempotente: si ya hay configuración guardada, no toca nada.
 */
class SiteSettingsSeeder extends Seeder
{
    public function run(): void
    {
        if (Setting::query()->where('key', 'site')->exists()) {
            return;
        }

        app(SiteSettings::class)->update([
            'title' => ['es' => 'Tu juego', 'eu' => 'Zure jokoa', 'en' => 'Your game'],
            'description' => ['es' => 'Un juego de mesa imprimible montado sobre EdC Motor.'],
            'logo' => $this->logo(),
            'favicon' => $this->favicon(),
            'accent_mode' => 'random',
            'accent_colors' => ['#6c5ce7'],
            'font_headings' => 'ebgaramond',
            'font_body' => 'lora',
            'font_special' => 'italianno', // solo la usa el bloque cita

            'footer_text' => [
                'es' => 'Hecho con EdC Motor',
                'eu' => 'EdC Motorrekin egina',
                'en' => 'Made with EdC Motor',
            ],
        ]);
    }

    /** Logo SVG sencillo: hereda el acento de la web vía currentColor. */
    protected function logo(): string
    {
        $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" height="32">
  <circle cx="16" cy="16" r="13" fill="none" stroke="currentColor" stroke-width="3"/>
  <path d="M16 7l3 6 6 1-4.5 4 1 6-5.5-3-5.5 3 1-6L7 14l6-1z" fill="currentColor"/>
</svg>
SVG;

        Storage::disk('public')->put('content/site-logo.svg', $svg);

        return Storage::disk('public')->url('content/site-logo.svg');
    }

    /** Favicon PNG (GD), o null sin la extensión. */
    protected function favicon(): ?string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $image = imagecreatetruecolor(64, 64);
        imagefill($image, 0, 0, imagecolorallocate($image, 26, 29, 36));
        imagefilledellipse($image, 32, 32, 52, 52, imagecolorallocate($image, 108, 92, 231));
        imagefilledellipse($image, 32, 32, 30, 30, imagecolorallocate($image, 255, 255, 255));

        ob_start();
        imagepng($image);
        Storage::disk('public')->put('content/site-favicon.png', (string) ob_get_clean());

        return Storage::disk('public')->url('content/site-favicon.png');
    }
}
