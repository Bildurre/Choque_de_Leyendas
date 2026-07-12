<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SortsIndex;
use App\Http\Resources\HeroSuperclassResource;
use App\Models\HeroSuperclass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/** CRUD de admin para HeroSuperclass (taxonomía simple, resuelta por id). */
class HeroSuperclassController extends Controller
{
    use SortsIndex;

    public function index(Request $request)
    {
        $superclasses = HeroSuperclass::query()
            ->filter($request->only('search', 'status'))
            ->tap(fn ($query) => $this->applySort($query, $request->query('sort')))
            ->paginate(15);

        return HeroSuperclassResource::collection($superclasses);
    }

    /** Lista ligera (id + nombre traducible) para selectores. */
    public function options()
    {
        return response()->json([
            'data' => HeroSuperclass::orderByDesc('id')->get()->map(fn (HeroSuperclass $s) => [
                'id' => $s->id,
                'name' => $s->getTranslations('name'),
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $superclass = new HeroSuperclass;
        $this->fill($superclass, $data);
        $superclass->save();

        return (new HeroSuperclassResource($superclass))->response()->setStatusCode(201);
    }

    public function update(Request $request, int $id)
    {
        $superclass = HeroSuperclass::findOrFail($id);
        $data = $this->validateData($request);
        $this->fill($superclass, $data);
        $superclass->save();

        return new HeroSuperclassResource($superclass);
    }

    public function destroy(int $id)
    {
        HeroSuperclass::findOrFail($id)->delete();

        return response()->noContent();
    }

    public function restore(int $id)
    {
        $superclass = HeroSuperclass::withTrashed()->findOrFail($id);
        $superclass->restore();

        return new HeroSuperclassResource($superclass);
    }

    /** Borrado definitivo (desde la papelera). */
    public function forceDestroy(int $id)
    {
        HeroSuperclass::withTrashed()->findOrFail($id)->forceDelete();

        return response()->noContent();
    }

    /** Valida el nombre traducible por locale (required en el default). */
    protected function validateData(Request $request): array
    {
        $default = config('motor.default_locale');
        $rules = [];
        foreach (array_keys(config('motor.locales', [])) as $locale) {
            $rules["name.$locale"] = [$locale === $default ? 'required' : 'nullable', 'string', 'max:255'];
        }

        return Validator::make($request->all(), $rules)->validate();
    }

    protected function fill(HeroSuperclass $superclass, array $data): void
    {
        $superclass->replaceTranslations('name', array_filter($data['name'] ?? [], fn ($v) => $v !== null && $v !== ''));
    }
}
