<?php

namespace App\Blocks;

use Edc\Core\Content\BlockType;

/**
 * Bloque con-datos de ESTE juego: monta el apartado público de Descargas
 * (los PDF permanentes agrupados por tipo + "tu colección") dentro de una
 * página del CRM. Sin configuración propia: el componente Vue de la app
 * (DownloadsBlock.vue) reutiliza el mismo contenido extraído que la vista
 * de descargas (DownloadsContent.vue), que carga GET /api/downloads.
 */
class DownloadsBlock extends BlockType
{
    public static string $key = 'downloads';

    public string $name = 'Descargas';

    public string $icon = 'download';

    public string $category = 'data';

    public function fields(): array
    {
        return [];
    }
}
