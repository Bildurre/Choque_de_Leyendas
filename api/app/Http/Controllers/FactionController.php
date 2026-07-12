<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SanitizesRichText;
use App\Http\Controllers\Concerns\SortsIndex;
use App\Http\Resources\FactionResource;
use App\Models\Faction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/** CRUD de admin para Faction (facción; por slug traducible). */
class FactionController extends Controller
{
    use SanitizesRichText;
    use SortsIndex;

    public function index(Request $request)
    {
        $factions = Faction::query()
            ->filter($request->only('search', 'status'))
            ->tap(fn ($query) => $this->applySort($query, $request->query('sort')))
            ->paginate(15);

        return FactionResource::collection($factions);
    }

    /** Lista ligera (id + nombre traducible + color) para selectores. */
    public function options()
    {
        return response()->json([
            'data' => Faction::orderByDesc('id')->get()->map(fn (Faction $faction) => [
                'id' => $faction->id,
                'name' => $faction->getTranslations('name'),
                'color' => $faction->color,
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $faction = new Faction;
        $this->fill($faction, $data);
        $faction->save();
        $faction->setImageFromRequest($request);
        // El icono (MediaLibrary) no es columna: no dispara la invalidación
        // declarativa. Se regenera a mano.
        $faction->regeneratePreviews();

        return (new FactionResource($faction))->response()->setStatusCode(201);
    }

    public function show(string $slug)
    {
        // TODO: cargar heroes/cards cuando existan sus clusters (single view).
        $faction = Faction::whereSlug($slug)->firstOrFail();

        return new FactionResource($faction);
    }

    public function update(Request $request, string $slug)
    {
        $faction = Faction::whereSlug($slug)->firstOrFail();
        $data = $this->validateData($request);
        $this->fill($faction, $data);
        $faction->save();
        $faction->setImageFromRequest($request);
        // El icono (MediaLibrary) no es columna: no dispara la invalidación
        // declarativa. Se regenera a mano.
        $faction->regeneratePreviews();

        return new FactionResource($faction);
    }

    public function destroy(string $slug)
    {
        Faction::whereSlug($slug)->firstOrFail()->delete();

        return response()->noContent();
    }

    public function restore(int $id)
    {
        $faction = Faction::withTrashed()->findOrFail($id);
        $faction->restore();

        return new FactionResource($faction);
    }

    /** Borrado definitivo (desde la papelera): elimina la fila y su icono. */
    public function forceDestroy(int $id)
    {
        $faction = Faction::withTrashed()->findOrFail($id);
        $faction->clearMediaCollection('image');
        $faction->forceDelete();

        return response()->noContent();
    }

    public function togglePublished(string $slug)
    {
        $faction = Faction::whereSlug($slug)->firstOrFail();
        $faction->togglePublished();

        return new FactionResource($faction);
    }

    /** Valida los campos traducibles por locale + color hex + icono opcional. */
    protected function validateData(Request $request): array
    {
        $default = config('motor.default_locale');
        $rules = [
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_published' => ['boolean'],
            'image' => ['nullable', 'image', 'max:4096'],
        ];
        foreach (array_keys(config('motor.locales', [])) as $locale) {
            $rules["name.$locale"] = [$locale === $default ? 'required' : 'nullable', 'string', 'max:255'];
            $rules["lore_text.$locale"] = ['nullable', 'string'];
            $rules["epic_quote.$locale"] = ['nullable', 'string'];
        }

        return Validator::make($request->all(), $rules)->validate();
    }

    protected function fill(Faction $faction, array $data): void
    {
        $faction->replaceTranslations('name', array_filter($data['name'] ?? [], fn ($v) => $v !== null && $v !== ''));
        $faction->replaceTranslations('lore_text', $this->cleanRich(array_filter($data['lore_text'] ?? [], fn ($v) => $v !== null && $v !== '')));
        $faction->replaceTranslations('epic_quote', $this->cleanRich(array_filter($data['epic_quote'] ?? [], fn ($v) => $v !== null && $v !== '')));
        $faction->color = $data['color'];
        if (array_key_exists('is_published', $data)) {
            $faction->is_published = (bool) $data['is_published'];
        }
        // text_is_dark lo calcula el modelo en saving().
    }
}
