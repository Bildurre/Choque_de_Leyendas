<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SanitizesRichText;
use App\Http\Controllers\Concerns\SortsIndex;
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
    use SortsIndex;

    public function index(Request $request)
    {
        $cards = Card::query()
            // El listado pinta el tipado completo (tipo, subtipo, equipo,
            // ataque) y el panel derecho el efecto con su habilidad otorgada.
            ->with($this->relations())
            ->filter($request->only('search', 'status'))
            // Filtros del listado (selects junto a la búsqueda).
            ->when(
                $request->filled('faction_id'),
                fn ($q) => $q->where('faction_id', $request->integer('faction_id'))
            )
            // Varias facciones a la vez (el editor de mazos acota los
            // disponibles a las facciones del mazo).
            ->when(
                is_array($request->input('faction_ids')),
                fn ($q) => $q->whereIn('faction_id', array_map('intval', $request->input('faction_ids', [])))
            )
            ->when(
                $request->filled('card_type_id'),
                fn ($q) => $q->where('card_type_id', $request->integer('card_type_id'))
            )
            // TODO filtro por coste (lo pedirá la vista como en el viejo):
            // ->when($request->filled('cost'),
            //     fn ($q) => $q->where('cost', Card::normalizeCost($request->string('cost'))))
            ->tap(fn ($query) => $this->applySort($query, $request->query('sort')))
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
        // Flags que condicionan el equipo: si el tipo de carta es equipo,
        // tipo y subtipo de equipo son obligatorios (todo equipo se tipa
        // "Equipo - tipo - subtipo"); si el tipo de equipo lleva manos
        // (armas), las manos también.
        $cardType = CardType::find($request->input('card_type_id'));
        $isEquipment = (bool) $cardType?->is_equipment;
        $equipment = $isEquipment
            ? EquipmentType::find($request->input('equipment_type_id'))
            : null;
        // El subtipo debe pertenecer al tipo de equipo elegido.
        $subtypeExists = Rule::exists('equipment_subtypes', 'id');
        if ($equipment) {
            $subtypeExists->where('equipment_type_id', $equipment->id);
        }
        $rules = [
            'faction_id' => ['required', 'integer', 'exists:factions,id'],
            'card_type_id' => ['required', 'integer', 'exists:card_types,id'],
            'card_subtype_id' => ['nullable', 'integer', 'exists:card_subtypes,id'],
            'equipment_type_id' => [
                $isEquipment ? 'required' : 'nullable',
                'integer', 'exists:equipment_types,id',
            ],
            'equipment_subtype_id' => [
                $isEquipment ? 'required' : 'nullable', 'integer', $subtypeExists,
            ],
            'attack_type' => ['nullable', 'string', Rule::in(Card::ATTACK_TYPES)],
            'attack_range_id' => ['nullable', 'integer', 'exists:attack_ranges,id'],
            'attack_subtype_id' => ['nullable', 'integer', 'exists:attack_subtypes,id'],
            'hero_ability_id' => ['nullable', 'integer', 'exists:hero_abilities,id'],
            'hands' => [
                // Obligatorias si el tipo de equipo lleva manos (armas).
                $equipment?->uses_hands ? 'required' : 'nullable',
                'integer', Rule::in(Card::HANDS),
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
        $card->faction_id = (int) $data['faction_id'];
        $card->card_type_id = (int) $data['card_type_id'];
        $card->card_subtype_id = $data['card_subtype_id'] ?? null;
        $card->equipment_type_id = $data['equipment_type_id'] ?? null;
        $card->equipment_subtype_id = $data['equipment_subtype_id'] ?? null;
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
            $card->equipment_subtype_id = null;
            $card->hands = null;
        }
        // Las manos solo aplican a tipos de equipo que las llevan (armas).
        if ($card->equipment_type_id && ! EquipmentType::find($card->equipment_type_id)?->uses_hands) {
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
            'equipmentSubtype',
            'attackRange',
            'attackSubtype',
            // Con su tipado: el single pinta la habilidad completa.
            'heroAbility.attackRange',
            'heroAbility.attackSubtype',
        ];
    }

    protected function loaded(Card $card): Card
    {
        return $card->load($this->relations());
    }
}
