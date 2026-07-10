<?php

namespace App\Models;

use App\Support\GameIcons;
use Edc\Core\Media\Concerns\HasImage;
use Edc\Core\Previews\Concerns\HasPreviewImage;
use Edc\Core\Previews\PreviewableContract;
use Edc\Core\Support\Concerns\HasFilters;
use Edc\Core\Support\Concerns\HasPublishedState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Translatable\HasTranslations;

/**
 * Contador (ficha de estado): beneficio ('boon') o perjuicio ('bane').
 * Sin slug: se resuelve por id. Renderizable a PNG (token 300x300).
 */
class Counter extends Model implements HasMedia, PreviewableContract
{
    use HasFilters;
    use HasImage;
    use HasPreviewImage;
    use HasPublishedState;
    use HasTranslations;
    use SoftDeletes;

    /** Tipos admitidos (validación in:... en el controller). */
    public const TYPES = ['boon', 'bane'];

    protected $table = 'counters';

    protected $fillable = ['name', 'effect', 'type', 'is_published'];

    public array $translatable = ['name', 'effect'];

    protected array $searchable = ['name'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    // --- Render a PNG: el token del contador ---

    /** Tamaño del token en px CSS (RENDER-SPEC: 300x300). */
    public function previewSize(?string $type = null): array
    {
        return ['width' => 300, 'height' => 300];
    }

    /** Etiqueta para el gestor de previews del admin. */
    public function previewLabel(string $locale): string
    {
        return $this->getTranslation('name', $locale) ?: "#{$this->id}";
    }

    /** Cambios que invalidan el token (declarativo; is_published no). */
    public function previewTriggerFields(): array
    {
        return ['name', 'effect', 'type'];
    }

    /** Payload que consume el componente del token en /_render. */
    public function renderData(string $locale, ?string $type = null): array
    {
        return [
            'id' => $this->id,
            'name' => $this->getTranslation('name', $locale),
            'image' => $this->imageUrl(),
            'type' => $this->type,
            'effect' => $this->getTranslation('effect', $locale),
            // Icono convencional del tipo (boon|bane); null si no está subido.
            'icons' => GameIcons::urls([$this->type]),
        ];
    }
}
