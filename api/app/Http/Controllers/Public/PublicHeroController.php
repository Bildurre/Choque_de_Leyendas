<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Public\PublicHeroResource;
use App\Models\Hero;

/**
 * Web pública: single de héroe. El índice lo sirve /api/catalog/hero (motor).
 * Solo lectura, sin auth, SOLO publicado; localizado por SetLocale.
 */
class PublicHeroController extends Controller
{
    /** Ficha por slug (vale en cualquier locale); 404 si no está publicado. */
    public function show(string $slug)
    {
        $hero = Hero::published()
            ->whereSlug($slug)
            ->with([
                'faction',
                'heroRace',
                'heroClass.heroSuperclass',
                'heroAbilities.attackRange',
                'heroAbilities.attackSubtype',
            ])
            ->firstOrFail();

        return new PublicHeroResource($hero);
    }
}
