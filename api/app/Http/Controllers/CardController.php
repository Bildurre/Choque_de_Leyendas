<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SanitizesRichText;
use App\Http\Resources\CardResource;
use App\Models\Card;
use App\Models\CardType;
use App\Models\EquipmentType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** CRUD de admin para Card (resuelta por slug; renderizable a carta PNG). */
class CardController extends Controller
{
    use SanitizesRichText;

    public function index(Request $request)
    {
        $cards = Card::query()
            ->with(['faction', 'cardType', 'cardSubtype'])
            ->filter($request->only('search', 'status'))
            // TODO filtros extra del listado (los pedirá la vista como en el
            // viejo: facción, tipo y coste). Descomentar cuando existan:
            // ->when($request->filled('faction_id'),
            //     fn ($q) => $q->where('faction_id', $request->integer('faction_id')))
            // ->when($request->filled('card_type_id'),
            //     fn ($q) => $q->where('card_type_id', $request->integer('card_type_id')))
            // ->when($request->filled('cost'),
            //     fn ($q) => $q->where('cost', Card::normalizeCost($request->string('cost'))))
            ->orderByDesc('id')
            ->paginate(15);

        return CardResource::collection($cards);
    }

    /** Lista ligera (id + nombre + slug traducibles) para selectores. */
    public function options()
    {
        return response()->json([
            'data' => Card::orderByDesc('id')->get()->map(fn (Card $card) => [
                'id' => $card->id,
                'name' => $card->getTranslations('name'),
                'slug' => $card->getTranslations('slug'),
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $card = new Card;
        $this->fill($card, $data);
        $card->save();
        $card->setImageFromRequest($request);
        if ($request->hasFile('image')) {
            // La imagen vive en MediaLibrary (no es columna): invalida a mano.
            $card->regeneratePreviews();
        }

        return (new CardResource($this->loaded($card)))->response()->setStatusCode(201);
    }

    public function show(string $slug)
    {
        $card = Card::with($this->relations())
            ->whereSlug($slug)
            ->firstOrFail();

        return new CardResource($card);
    }

    public function update(Request $request, string $slug)
    {
        $card = Card::whereSlug($slug)->firstOrFail();
        $data = $this->validateData($request);
        $this->fill($card, $data);
        $card->save();
        $card->setImageFromRequest($request);
        if ($request->hasFile('image')) {
            // La imagen vive en MediaLibrary (no es columna): invalida a mano.
            $card->regeneratePreviews();
        }

        return new CardResource($this->loaded($card));
    }

    public function destroy(string $slug)
    {
        Card::whereSlug($slug)->firstOrFail()->delete();

        return response()->noContent();
    }

    public function restore(int $id)
    {
        $card = Card::withTrashed()->findOrFail($id);
        $card->restore();

        return new CardResource($card);
    }

    /** Borrado definitivo (desde la papelera): elimina la fila y su imagen. */
    public function forceDestroy(int $id)
    {
        $card = Card::withTrashed()->findOrFail($id);
        $card->clearMediaCollection('image');
        $card->forceDelete();

        return response()->noContent();
    }

    public function togglePublished(string $slug)
    {
        $card = Card::whereSlug($slug)->firstOrFail();
        $card->togglePublished();

        return new CardResource($card);
    }

    /** Traducibles por locale + relaciones + coste (HasCost) + flags del tipo. */
    protected function validateData(Request $request): array
    {
        $default = config('motor.default_locale');
        $rules = [
            'faction_id' => ['nullable', 'integer', 'exists:factions,id'],
            'card_type_id' => ['required', 'integer', 'exists:card_types,id'],
            'card_subtype_id' => ['nullable', 'integer', 'exists:card_subtypes,id'],
            'equipment_type_id' => ['nullable', 'integer', 'exists:equipment_types,id'],
            'attack_type' => ['nullable', 'string', Rule::in(Card::ATTACK_TYPES)],
            'attack_range_id' => ['nullable', 'integer', 'exists:attack_ranges,id'],
            'attack_subtype_id' => ['nullable', 'integer', 'exists:attack_subtypes,id'],
            'hero_ability_id' => ['nullable', 'integer', 'exists:hero_abilities,id'],
            'hands' => [
                'nullable', 'integer', Rule::in(Card::HANDS),
                // Obligatorias si el equipo es un arma (regla del viejo).
                function ($attribute, $value, $fail) use ($request) {
                    $equipment = EquipmentType::find($request->input('equipment_type_id'));
                    if ($equipment?->category === 'weapon' && $value === null) {
                        $fail(__('Hands are required for weapons.'));
                    }
                },
            ],
            'cost' => Card::costRules(),
            'area' => ['boolean'],
            'is_unique' => ['boolean'],
            'is_published' => ['boolean'],
            'image' => ['nullable', 'image', 'max:4096'],
        ];
        foreach (array_keys(config('motor.locales', [])) as $locale) {
            $rules["name.$locale"] = [$locale === $default ? 'required' : 'nullable', 'string', 'max:255'];
            $rules["lore_text.$locale"] = ['nullable', 'string'];
            $rules["epic_quote.$locale"] = ['nullable', 'string'];
            $rules["effect.$locale"] = ['nullable', 'string'];
            $rules["restriction.$locale"] = ['nullable', 'string'];
        }

        return Validator::make($request->all(), $rules)->validate();
    }

    protected function fill(Card $card, array $data): void
    {
        foreach (['name'] as $field) {
            $card->replaceTranslations($field, array_filter($data[$field] ?? [], fn ($v) => $v !== null && $v !== ''));
        }
        // Campos wysiwyg: saneados por lista blanca (se pintan con v-html).
        foreach (['lore_text', 'epic_quote', 'effect', 'restriction'] as $field) {
            $card->replaceTranslations($field, $this->cleanRich(array_filter($data[$field] ?? [], fn ($v) => $v !== null && $v !== '')));
        }
        $card->faction_id = $data['faction_id'] ?? null;
        $card->card_type_id = (int) $data['card_type_id'];
        $card->card_subtype_id = $data['card_subtype_id'] ?? null;
        $card->equipment_type_id = $data['equipment_type_id'] ?? null;
        $card->attack_type = $data['attack_type'] ?? null;
        $card->attack_range_id = $data['attack_range_id'] ?? null;
        $card->attack_subtype_id = $data['attack_subtype_id'] ?? null;
        $card->hero_ability_id = $data['hero_ability_id'] ?? null;
        $card->hands = $data['hands'] ?? null;
        $card->cost = $data['cost'] ?? null;
        $card->area = (bool) ($data['area'] ?? false);
        $card->is_unique = (bool) ($data['is_unique'] ?? false);
        if (array_key_exists('is_published', $data)) {
            $card->is_published = (bool) $data['is_published'];
        }

        // Coherencia con los flags del tipo (el form ya oculta estos campos;
        // aquí se anula lo que no aplique, en vez de fallar).
        $type = CardType::find($card->card_type_id);
        if (! $type?->allows_subtypes) {
            $card->card_subtype_id = null;
        }
        if (! $type?->is_equipment) {
            $card->equipment_type_id = null;
            $card->hands = null;
        }
    }

    /** Relaciones que pinta el admin (Resource con whenLoaded). */
    protected function relations(): array
    {
        return [
            'faction',
            'cardType',
            'cardSubtype',
            'equipmentType',
            'attackRange',
            'attackSubtype',
            'heroAbility',
        ];
    }

    protected function loaded(Card $card): Card
    {
        return $card->load($this->relations());
    }
}
