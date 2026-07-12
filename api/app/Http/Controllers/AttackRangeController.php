<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SortsIndex;
use App\Http\Resources\AttackRangeResource;
use App\Models\AttackRange;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/** CRUD de admin para AttackRange (taxonomía simple, resuelta por id). */
class AttackRangeController extends Controller
{
    use SortsIndex;

    public function index(Request $request)
    {
        $ranges = AttackRange::query()
            ->filter($request->only('search', 'status'))
            ->tap(fn ($query) => $this->applySort($query, $request->query('sort')))
            ->paginate(15);

        return AttackRangeResource::collection($ranges);
    }

    /** Lista ligera (id + nombre traducible) para selectores. */
    public function options()
    {
        return response()->json([
            'data' => AttackRange::orderByDesc('id')->get()->map(fn (AttackRange $r) => [
                'id' => $r->id,
                'name' => $r->getTranslations('name'),
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $range = new AttackRange;
        $this->fill($range, $data);
        $range->save();

        return (new AttackRangeResource($range))->response()->setStatusCode(201);
    }

    public function update(Request $request, int $id)
    {
        $range = AttackRange::findOrFail($id);
        $data = $this->validateData($request);
        $this->fill($range, $data);
        $range->save();

        return new AttackRangeResource($range);
    }

    public function destroy(int $id)
    {
        AttackRange::findOrFail($id)->delete();

        return response()->noContent();
    }

    public function restore(int $id)
    {
        $range = AttackRange::withTrashed()->findOrFail($id);
        $range->restore();

        return new AttackRangeResource($range);
    }

    /** Borrado definitivo (desde la papelera). */
    public function forceDestroy(int $id)
    {
        AttackRange::withTrashed()->findOrFail($id)->forceDelete();

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

    protected function fill(AttackRange $range, array $data): void
    {
        $range->replaceTranslations('name', array_filter($data['name'] ?? [], fn ($v) => $v !== null && $v !== ''));
    }
}
