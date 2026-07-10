<?php

namespace App\Http\Controllers\Concerns;

use Edc\Core\Support\HtmlSanitizer;

// Los campos wysiwyg se guardan saneados por lista blanca, como los bloques
// del CRM (DC-09): el admin y los renders los pintan con v-html y no deben
// confiar en lo que llegue del formulario.
trait SanitizesRichText
{
    /** Sanea un mapa {locale => html} de un campo traducible rico. */
    protected function cleanRich(array $map): array
    {
        $sanitizer = app(HtmlSanitizer::class);

        return array_map(
            fn ($html) => is_string($html) ? $sanitizer->clean($html) : $html,
            $map,
        );
    }
}
