<?php

namespace App\Http\Resources\Public;

use App\Models\Card;
use App\Models\Hero;
use App\Support\PublicCatalogItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Ficha pública de mazo: listas de héroes y cartas (con copias, ordenadas
 * por coste y nombre como el viejo) + estadísticas portadas del
 * FactionDeckService viejo (dados, símbolos, tipos, clases). Las relaciones
 * llegan YA filtradas a publicado desde el controlador; los totales se
 * calculan sobre eso. name/slug/description por locales (EntityDetailView).
 */
class PublicFactionDeckResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();

        // Orden del viejo: coste (clave estable R/G/B) y nombre localizado
        $cards = $this->cards
            ->sortBy(fn (Card $card) => $card->cost_order.'|'.mb_strtolower($card->getTranslation('name', $locale)))
            ->values();

        return [
            'id' => $this->id,
            'name' => $this->getTranslations('name'),
            'slug' => $this->getTranslations('slug'),
            'description' => $this->getTranslations('description'),
            'icon' => $this->imageUrl(),
            // Fondo de página: icono del mazo o el de su facción primaria
            'image' => $this->imageUrl() ?: $this->factions->first()?->imageUrl(),
            'epic_quote' => $this->getTranslation('epic_quote', $locale),
            'game_mode' => $this->gameMode ? [
                'id' => $this->gameMode->id,
                'name' => $this->gameMode->getTranslation('name', $locale),
            ] : null,
            'factions' => $this->factions
                ->map(fn ($faction) => PublicFactionItemResource::ref($faction, $locale))
                ->values(),
            'heroes' => $this->heroes->map(fn ($hero) => [
                ...PublicCatalogItem::fromModel($hero, 'hero', $locale),
                'copies' => (int) $hero->pivot->copies,
            ])->values(),
            'cards' => $cards->map(fn (Card $card) => [
                ...PublicCatalogItem::fromModel($card, 'card', $locale),
                'copies' => (int) $card->pivot->copies,
            ])->values(),
            'total_heroes' => (int) $this->heroes->sum('pivot.copies'),
            'total_cards' => (int) $cards->sum('pivot.copies'),
            'stats' => $this->stats($cards, $locale),
        ];
    }

    /** Estadísticas del mazo (portadas del FactionDeckService del viejo). */
    protected function stats(Collection $cards, string $locale): array
    {
        $totalCards = 0;
        $totalDice = 0;
        $byDice = [];
        $symbols = ['R' => 0, 'G' => 0, 'B' => 0];
        $byType = [];

        foreach ($cards as $card) {
            $copies = (int) $card->pivot->copies;
            $dice = $card->cost_total;

            $totalCards += $copies;
            $totalDice += $dice * $copies;
            $byDice[$dice] = ($byDice[$dice] ?? 0) + $copies;

            foreach (str_split((string) $card->cost) as $symbol) {
                if (isset($symbols[$symbol])) {
                    $symbols[$symbol] += $copies;
                }
            }

            $typeName = $card->cardType?->getTranslation('name', $locale) ?? '';
            $byType[$typeName] = ($byType[$typeName] ?? 0) + $copies;
        }
        ksort($byDice);

        $byClass = [];
        $bySuperclass = [];
        foreach ($this->heroes as $hero) {
            /** @var Hero $hero */
            $copies = (int) $hero->pivot->copies;
            $className = $hero->heroClass?->getTranslation('name', $locale) ?? '';
            $superclassName = $hero->heroClass?->heroSuperclass?->getTranslation('name', $locale) ?? '';
            $byClass[$className] = ($byClass[$className] ?? 0) + $copies;
            $bySuperclass[$superclassName] = ($bySuperclass[$superclassName] ?? 0) + $copies;
        }

        // Listas {name|dice, ...} en vez de mapas por nombre localizado:
        // orden estable para el front sin depender de claves traducidas.
        return [
            'total_cards' => $totalCards,
            'unique_cards' => $cards->count(),
            'average_dice' => $totalCards > 0 ? round($totalDice / $totalCards, 2) : 0,
            'cards_by_dice' => collect($byDice)
                ->map(fn (int $copies, int $dice) => ['dice' => $dice, 'copies' => $copies])
                ->values()
                ->all(),
            'symbols' => $symbols,
            'cards_by_type' => collect($byType)
                ->map(fn (int $copies, string $name) => ['name' => $name, 'copies' => $copies])
                ->values()
                ->all(),
            'total_heroes' => (int) $this->heroes->sum('pivot.copies'),
            'unique_heroes' => $this->heroes->count(),
            'heroes_by_class' => collect($byClass)
                ->map(fn (int $count, string $name) => ['name' => $name, 'count' => $count])
                ->values()
                ->all(),
            'heroes_by_superclass' => collect($bySuperclass)
                ->map(fn (int $count, string $name) => ['name' => $name, 'count' => $count])
                ->values()
                ->all(),
        ];
    }
}
