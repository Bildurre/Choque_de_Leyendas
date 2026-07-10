<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SanitizesRichText;
use App\Http\Resources\CounterResource;
use App\Models\Counter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** CRUD de admin para Counter (contador; sin slug, por id). */
class CounterController extends Controller
{
    use SanitizesRichText;

    public function index(Request $request)
    {
        $type = $request->query('type');

        $counters = Counter::query()
            ->filter($request->only('search', 'status'))
            // Filtro por tipo del listado (select junto a la búsqueda).
            ->when(
                in_array($type, Counter::TYPES, true),
                fn ($query) => $query->where('type', $type),
            )
            ->orderByDesc('id')
            ->paginate(15);

        return CounterResource::collection($counters);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $counter = new Counter;
        $this->fill($counter, $data);
        $counter->save();
        $counter->setImageFromRequest($request);
        if ($request->hasFile('image')) {
            // La imagen vive en MediaLibrary (no es columna): invalida a mano.
            $counter->regeneratePreviews();
        }

        return (new CounterResource($counter))->response()->setStatusCode(201);
    }

    public function show(int $id)
    {
        return new CounterResource(Counter::findOrFail($id));
    }

    public function update(Request $request, int $id)
    {
        $counter = Counter::findOrFail($id);
        $data = $this->validateData($request);
        $this->fill($counter, $data);
        $counter->save();
        $counter->setImageFromRequest($request);
        if ($request->hasFile('image')) {
            // La imagen vive en MediaLibrary (no es columna): invalida a mano.
            $counter->regeneratePreviews();
        }

        return new CounterResource($counter);
    }

    public function destroy(int $id)
    {
        Counter::findOrFail($id)->delete();

        return response()->noContent();
    }

    public function restore(int $id)
    {
        $counter = Counter::withTrashed()->findOrFail($id);
        $counter->restore();

        return new CounterResource($counter);
    }

    /** Borrado definitivo (desde la papelera): elimina fila, imagen y PNG. */
    public function forceDestroy(int $id)
    {
        $counter = Counter::withTrashed()->findOrFail($id);
        $counter->clearMediaCollection('image');
        $counter->forceDelete();

        return response()->noContent();
    }

    public function togglePublished(int $id)
    {
        $counter = Counter::findOrFail($id);
        $counter->togglePublished();

        return new CounterResource($counter);
    }

    /** Valida nombre/efecto por locale + tipo (boon|bane) + icono opcional. */
    protected function validateData(Request $request): array
    {
        $default = config('motor.default_locale');
        $rules = [
            'type' => ['required', 'string', Rule::in(Counter::TYPES)],
            'is_published' => ['boolean'],
            'image' => ['nullable', 'image', 'max:4096'],
        ];
        foreach (array_keys(config('motor.locales', [])) as $locale) {
            $rules["name.$locale"] = [$locale === $default ? 'required' : 'nullable', 'string', 'max:255'];
            $rules["effect.$locale"] = ['nullable', 'string'];
        }

        return Validator::make($request->all(), $rules)->validate();
    }

    protected function fill(Counter $counter, array $data): void
    {
        $counter->replaceTranslations('name', array_filter($data['name'] ?? [], fn ($v) => $v !== null && $v !== ''));
        $counter->replaceTranslations('effect', $this->cleanRich(array_filter($data['effect'] ?? [], fn ($v) => $v !== null && $v !== '')));
        $counter->type = $data['type'];
        if (array_key_exists('is_published', $data)) {
            $counter->is_published = (bool) $data['is_published'];
        }
    }
}
