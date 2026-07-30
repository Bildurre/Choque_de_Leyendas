<?php

namespace App\Blocks;

use Edc\Core\Content\BlockType;
use Edc\Core\Content\Fields\Field;

/**
 * Bloque con-datos de ESTE juego: monta el ÍNDICE COMPLETO de una sección
 * pública de entidades (búsqueda, filtros de la barra derecha y rejilla
 * paginada) dentro de una página del CRM. Solo elige la sección: el
 * componente Vue de la app (EntityIndexBlock.vue) reutiliza el mismo
 * catálogo extraído que la vista índice correspondiente, y el estado
 * (búsqueda/página/filtros) sigue viajando por la query string. Las claves
 * del select casan con `entitySections` de la app (entities/registry.ts).
 */
class EntityIndexBlock extends BlockType
{
    public static string $key = 'entity-index';

    public string $name = 'Índice de entidad';

    public string $icon = 'layout-grid';

    public string $category = 'data';

    public function fields(): array
    {
        return [
            Field::select('section', [
                'cards' => 'Cartas',
                'heroes' => 'Héroes',
                'factions' => 'Facciones',
                'decks' => 'Mazos',
            ])->label('Sección'),
        ];
    }
}
