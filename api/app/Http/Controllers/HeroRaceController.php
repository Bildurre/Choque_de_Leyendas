<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SortsIndex;
use App\Http\Resources\HeroRaceResource;
use App\Models\HeroRace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/** CRUD de admin para HeroRace (taxonomía simple, resuelta por id). */
class HeroRaceController extends Controller
{
    use SortsIndex;

    public function index(Request $request)
    {
        $races = HeroRace::query()
            ->filter($request->only('search', 'status'))
            ->tap(fn ($query) => $this->applySort($query, $request->query('sort')))
            ->paginate(15);

        return HeroRaceResource::collection($races);
    }

    /** Lista ligera (id + nombre traducible) para selectores. */
    public function options()
    {
        return response()->json([
            'data' => HeroRace::orderByDesc('id')->get()->map(fn (HeroRace $r) => [
                'id' => $r->id,
                'name' => $r->getTranslations('name'),
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $race = new HeroRace;
        $this->fill($race, $data);
        $race->save();

        return (new HeroRaceResource($race))->response()->setStatusCode(201);
    }

    public function update(Request $request, int $id)
    {
        $race = HeroRace::findOrFail($id);
        $data = $this->validateData($request);
        $this->fill($race, $data);
        $race->save();

        return new HeroRaceResource($race);
    }

    public function destroy(int $id)
    {
        HeroRace::findOrFail($id)->delete();

        return response()->noContent();
    }

    public function restore(int $id)
    {
        $race = HeroRace::withTrashed()->findOrFail($id);
        $race->restore();

        return new HeroRaceResource($race);
    }

    /** Borrado definitivo (desde la papelera). */
    public function forceDestroy(int $id)
    {
        HeroRace::withTrashed()->findOrFail($id)->forceDelete();

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

    protected function fill(HeroRace $race, array $data): void
    {
        $race->replaceTranslations('name', array_filter($data['name'] ?? [], fn ($v) => $v !== null && $v !== ''));
    }
}
