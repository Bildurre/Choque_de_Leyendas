<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SortsIndex;
use App\Http\Resources\CardSubtypeResource;
use App\Models\CardSubtype;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/** CRUD de admin para los subtipos de carta (taxonomía sin slug: por id). */
class CardSubtypeController extends Controller
{
    use SortsIndex;

    public function index(Request $request)
    {
        $subtypes = CardSubtype::query()
            ->filter($request->only('search', 'status'))
            ->tap(fn ($query) => $this->applySort($query, $request->query('sort')))
            ->paginate(15);

        return CardSubtypeResource::collection($subtypes);
    }

    /** Lista ligera (id + nombre traducible) para selectores. */
    public function options()
    {
        return response()->json([
            'data' => CardSubtype::orderByDesc('id')->get()->map(fn (CardSubtype $subtype) => [
                'id' => $subtype->id,
                'name' => $subtype->getTranslations('name'),
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $subtype = new CardSubtype;
        $this->fill($subtype, $data);
        $subtype->save();

        return (new CardSubtypeResource($subtype))->response()->setStatusCode(201);
    }

    public function show(int $id)
    {
        return new CardSubtypeResource(CardSubtype::findOrFail($id));
    }

    public function update(Request $request, int $id)
    {
        $subtype = CardSubtype::findOrFail($id);
        $data = $this->validateData($request);
        $this->fill($subtype, $data);
        $subtype->save();

        return new CardSubtypeResource($subtype);
    }

    public function destroy(int $id)
    {
        CardSubtype::findOrFail($id)->delete();

        return response()->noContent();
    }

    public function restore(int $id)
    {
        $subtype = CardSubtype::withTrashed()->findOrFail($id);
        $subtype->restore();

        return new CardSubtypeResource($subtype);
    }

    /** Borrado definitivo (desde la papelera). */
    public function forceDestroy(int $id)
    {
        CardSubtype::withTrashed()->findOrFail($id)->forceDelete();

        return response()->noContent();
    }

    /** Valida el nombre por locale (default required). */
    protected function validateData(Request $request): array
    {
        $default = config('motor.default_locale');
        $rules = [];
        foreach (array_keys(config('motor.locales', [])) as $locale) {
            $rules["name.$locale"] = [$locale === $default ? 'required' : 'nullable', 'string', 'max:255'];
        }

        return Validator::make($request->all(), $rules)->validate();
    }

    protected function fill(CardSubtype $subtype, array $data): void
    {
        $subtype->replaceTranslations('name', array_filter($data['name'] ?? [], fn ($v) => $v !== null && $v !== ''));
    }
}
