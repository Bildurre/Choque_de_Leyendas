<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SanitizesRichText;
use App\Http\Controllers\Concerns\SortsIndex;
use App\Http\Resources\HeroClassResource;
use App\Models\HeroClass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/** CRUD de admin para HeroClass (taxonomía con pasiva y superclase, por id). */
class HeroClassController extends Controller
{
    use SanitizesRichText;
    use SortsIndex;

    public function index(Request $request)
    {
        $classes = HeroClass::query()
            ->with('heroSuperclass')
            ->filter($request->only('search', 'status'))
            ->tap(fn ($query) => $this->applySort($query, $request->query('sort')))
            ->paginate(15);

        return HeroClassResource::collection($classes);
    }

    /** Lista ligera (id + nombre traducible) para selectores. */
    public function options()
    {
        return response()->json([
            'data' => HeroClass::orderByDesc('id')->get()->map(fn (HeroClass $c) => [
                'id' => $c->id,
                'name' => $c->getTranslations('name'),
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $class = new HeroClass;
        $this->fill($class, $data);
        $class->save();

        return (new HeroClassResource($class->load('heroSuperclass')))->response()->setStatusCode(201);
    }

    public function update(Request $request, int $id)
    {
        $class = HeroClass::findOrFail($id);
        $data = $this->validateData($request);
        $this->fill($class, $data);
        $class->save();

        return new HeroClassResource($class->load('heroSuperclass'));
    }

    public function destroy(int $id)
    {
        HeroClass::findOrFail($id)->delete();

        return response()->noContent();
    }

    public function restore(int $id)
    {
        $class = HeroClass::withTrashed()->findOrFail($id);
        $class->restore();

        return new HeroClassResource($class);
    }

    /** Borrado definitivo (desde la papelera). */
    public function forceDestroy(int $id)
    {
        HeroClass::withTrashed()->findOrFail($id)->forceDelete();

        return response()->noContent();
    }

    /** Valida traducibles por locale + la superclase opcional. */
    protected function validateData(Request $request): array
    {
        $default = config('motor.default_locale');
        $rules = [
            'hero_superclass_id' => ['nullable', 'integer', 'exists:hero_superclasses,id'],
        ];
        foreach (array_keys(config('motor.locales', [])) as $locale) {
            $rules["name.$locale"] = [$locale === $default ? 'required' : 'nullable', 'string', 'max:255'];
            $rules["passive.$locale"] = ['nullable', 'string'];
        }

        return Validator::make($request->all(), $rules)->validate();
    }

    protected function fill(HeroClass $class, array $data): void
    {
        $class->replaceTranslations('name', array_filter($data['name'] ?? [], fn ($v) => $v !== null && $v !== ''));
        $class->replaceTranslations('passive', $this->cleanRich(array_filter($data['passive'] ?? [], fn ($v) => $v !== null && $v !== '')));
        $class->hero_superclass_id = $data['hero_superclass_id'] ?? null;
    }
}
