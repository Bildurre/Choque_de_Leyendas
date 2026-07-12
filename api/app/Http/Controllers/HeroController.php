<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SanitizesRichText;
use App\Http\Controllers\Concerns\SortsIndex;
use App\Http\Resources\HeroResource;
use App\Models\Hero;
use App\Models\HeroAttributesConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/** CRUD de admin para Hero (resuelto por slug; renderizable a carta PNG). */
class HeroController extends Controller
{
    use SanitizesRichText;
    use SortsIndex;

    public function index(Request $request)
    {
        $heroes = Hero::query()
            ->with(['faction', 'heroRace', 'heroClass'])
            ->filter($request->only('search', 'status'))
            ->tap(fn ($query) => $this->applySort($query, $request->query('sort')))
            ->paginate(15);

        return HeroResource::collection($heroes);
    }

    /** Lista ligera (id + nombre + slug traducibles) para selectores. */
    public function options()
    {
        return response()->json([
            'data' => Hero::orderByDesc('id')->get()->map(fn (Hero $h) => [
                'id' => $h->id,
                'name' => $h->getTranslations('name'),
                'slug' => $h->getTranslations('slug'),
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $hero = new Hero;
        $this->fill($hero, $data);
        $hero->save();
        $this->syncAbilities($hero, $data);
        $hero->setImageFromRequest($request);
        // Imagen (MediaLibrary) y habilidades (pivot) no son columnas: no
        // disparan la invalidación declarativa. Se regenera a mano.
        $hero->regeneratePreviews();

        return (new HeroResource($this->loaded($hero)))->response()->setStatusCode(201);
    }

    public function show(string $slug)
    {
        $hero = Hero::with(['faction', 'heroRace', 'heroClass', 'heroAbilities'])
            ->whereSlug($slug)
            ->firstOrFail();

        return new HeroResource($hero);
    }

    public function update(Request $request, string $slug)
    {
        $hero = Hero::whereSlug($slug)->firstOrFail();
        $data = $this->validateData($request);
        $this->fill($hero, $data);
        $hero->save();
        $this->syncAbilities($hero, $data);
        $hero->setImageFromRequest($request);
        // Imagen (MediaLibrary) y habilidades (pivot) no son columnas: no
        // disparan la invalidación declarativa. Se regenera a mano.
        $hero->regeneratePreviews();

        return new HeroResource($this->loaded($hero));
    }

    public function destroy(string $slug)
    {
        Hero::whereSlug($slug)->firstOrFail()->delete();

        return response()->noContent();
    }

    public function restore(int $id)
    {
        $hero = Hero::withTrashed()->findOrFail($id);
        $hero->restore();

        return new HeroResource($hero);
    }

    /** Borrado definitivo (desde la papelera): elimina la fila y su imagen. */
    public function forceDestroy(int $id)
    {
        $hero = Hero::withTrashed()->findOrFail($id);
        $hero->clearMediaCollection('image');
        $hero->forceDelete();

        return response()->noContent();
    }

    public function togglePublished(string $slug)
    {
        $hero = Hero::whereSlug($slug)->firstOrFail();
        $hero->togglePublished();

        return new HeroResource($hero);
    }

    /** Traducibles por locale + relaciones + atributos según la config. */
    protected function validateData(Request $request): array
    {
        $config = HeroAttributesConfiguration::getDefault();
        $attributeRules = [
            'required', 'integer',
            'min:'.$config->min_attribute_value,
            'max:'.$config->max_attribute_value,
        ];

        $default = config('motor.default_locale');
        $rules = [
            'faction_id' => ['nullable', 'integer', 'exists:factions,id'],
            'hero_race_id' => ['nullable', 'integer', 'exists:hero_races,id'],
            'hero_class_id' => ['nullable', 'integer', 'exists:hero_classes,id'],
            'gender' => ['required', 'string', 'in:male,female'],
            'agility' => $attributeRules,
            'mental' => $attributeRules,
            'will' => $attributeRules,
            'strength' => $attributeRules,
            'armor' => $attributeRules,
            'abilities' => ['nullable', 'array'],
            'abilities.*.id' => ['required', 'integer', 'exists:hero_abilities,id'],
            'abilities.*.position' => ['required', 'integer', 'min:1'],
            'is_published' => ['boolean'],
            'image' => ['nullable', 'image', 'max:4096'],
        ];
        foreach (array_keys(config('motor.locales', [])) as $locale) {
            $rules["name.$locale"] = [$locale === $default ? 'required' : 'nullable', 'string', 'max:255'];
            $rules["lore_text.$locale"] = ['nullable', 'string'];
            $rules["epic_quote.$locale"] = ['nullable', 'string'];
            $rules["passive_name.$locale"] = ['nullable', 'string', 'max:255'];
            $rules["passive_description.$locale"] = ['nullable', 'string'];
        }

        return Validator::make($request->all(), $rules)->validate();
    }

    protected function fill(Hero $hero, array $data): void
    {
        foreach (['name', 'passive_name'] as $field) {
            $hero->replaceTranslations($field, array_filter($data[$field] ?? [], fn ($v) => $v !== null && $v !== ''));
        }
        // Campos wysiwyg: saneados por lista blanca (se pintan con v-html).
        foreach (['lore_text', 'epic_quote', 'passive_description'] as $field) {
            $hero->replaceTranslations($field, $this->cleanRich(array_filter($data[$field] ?? [], fn ($v) => $v !== null && $v !== '')));
        }
        $hero->faction_id = $data['faction_id'] ?? null;
        $hero->hero_race_id = $data['hero_race_id'] ?? null;
        $hero->hero_class_id = $data['hero_class_id'] ?? null;
        $hero->gender = $data['gender'];
        foreach (['agility', 'mental', 'will', 'strength', 'armor'] as $attribute) {
            $hero->{$attribute} = (int) $data[$attribute];
        }
        if (array_key_exists('is_published', $data)) {
            $hero->is_published = (bool) $data['is_published'];
        }
    }

    /** Sincroniza las habilidades activas con su posición ([{id, position}]). */
    protected function syncAbilities(Hero $hero, array $data): void
    {
        if (! array_key_exists('abilities', $data)) {
            return;
        }

        $payload = collect($data['abilities'] ?? [])
            ->mapWithKeys(fn (array $row) => [(int) $row['id'] => ['position' => (int) $row['position']]])
            ->all();

        $hero->heroAbilities()->sync($payload);
    }

    /** El héroe con todo lo que pinta el admin tras guardar. */
    protected function loaded(Hero $hero): Hero
    {
        return $hero->load(['faction', 'heroRace', 'heroClass', 'heroAbilities']);
    }
}
