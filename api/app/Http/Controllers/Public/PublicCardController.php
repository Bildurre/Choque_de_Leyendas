<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Public\PublicCardResource;
use App\Models\Card;

/**
 * Web pública: single de carta. El índice lo sirve /api/catalog/card (motor).
 * Solo lectura, sin auth, SOLO publicado; localizado por SetLocale.
 */
class PublicCardController extends Controller
{
    /** Ficha por slug (vale en cualquier locale); 404 si no está publicada. */
    public function show(string $slug)
    {
        $card = Card::published()
            ->whereSlug($slug)
            ->with([
                'faction',
                'cardType.heroSuperclass',
                'cardSubtype',
                'equipmentType',
                'attackRange',
                'attackSubtype',
                'heroAbility.attackRange',
                'heroAbility.attackSubtype',
            ])
            ->firstOrFail();

        return new PublicCardResource($card);
    }
}
