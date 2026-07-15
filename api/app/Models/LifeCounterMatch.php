<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Partida del contador de vidas (herramienta de la web pública): histórico
 * por usuario registrado. El estado (equipos: facciones + héroes con sus
 * vidas) es un json opaco que compone el cliente; el servidor solo lo
 * persiste para retomar la partida activa o consultar las terminadas.
 */
class LifeCounterMatch extends Model
{
    /** Estados admitidos (validación in:... en el controller). */
    public const STATUSES = ['active', 'finished'];

    protected $fillable = ['user_id', 'state', 'status'];

    protected function casts(): array
    {
        return ['state' => 'array'];
    }

    /** Dueño de la partida (solo él la ve y la edita). */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
