<?php

namespace Database\Seeders;

use Edc\Core\Content\Models\Block;
use Edc\Core\Content\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Home mínima del CRM: una portada con bloques de presentación del motor,
 * para que la web arranque con algo que ver. Sustitúyela por las páginas de
 * tu juego desde el admin (sección Páginas) y vuelca lo definitivo aquí.
 * Idempotente: si ya hay páginas, no toca nada.
 */
class PagesSeeder extends Seeder
{
    public function run(): void
    {
        if (Page::count() > 0) {
            return;
        }

        $home = new Page;
        $home->setTranslations('title', ['es' => 'Bienvenida', 'eu' => 'Ongi etorri', 'en' => 'Welcome']);
        $home->is_published = true;
        $home->save();
        $home->forceFill(['is_home' => true, 'template' => 'landing'])->save();

        $bloques = [
            ['header', [
                'title' => ['es' => 'Tu juego', 'eu' => 'Zure jokoa', 'en' => 'Your game'],
                'subtitle' => [
                    'es' => 'Una web montada sobre EdC Motor',
                    'eu' => 'EdC Motorren gainean eraikitako weba',
                    'en' => 'A website built on EdC Motor',
                ],
                'align' => 'center',
                'width' => 'full',
            ]],
            ['text', [
                'body' => [
                    'es' => '<p>Esta portada es el bloque de partida: edítala desde el admin (sección <strong>Páginas</strong>) y crea las entidades de tu juego siguiendo la guía.</p>',
                    'en' => '<p>This home page is the starting block: edit it from the admin (<strong>Pages</strong>) and build your game entities following the guide.</p>',
                ],
                'align' => 'center',
                'width' => 'narrow',
            ]],
        ];

        foreach ($bloques as $order => [$type, $settings]) {
            Block::create([
                'page_id' => $home->id,
                'type' => $type,
                'order' => $order,
                'settings' => $settings,
            ]);
        }
    }
}
