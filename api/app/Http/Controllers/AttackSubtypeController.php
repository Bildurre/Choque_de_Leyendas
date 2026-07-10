<?php

namespace App\Http\Controllers;

use App\Http\Resources\AttackSubtypeResource;
use App\Models\AttackSubtype;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/** CRUD de admin para AttackSubtype (taxonomía simple, resuelta por id). */
class AttackSubtypeController extends Controller
{
    public function index(Request $request)
    {
        $subtypes = AttackSubtype::query()
            ->filter($request->only('search', 'status'))
            ->orderByDesc('id')
            ->paginate(15);

        return AttackSubtypeResource::collection($subtypes);
    }

    /** Lista ligera (id + nombre traducible) para selectores. */
    public function options()
    {
        return response()->json([
            'data' => AttackSubtype::orderByDesc('id')->get()->map(fn (AttackSubtype $s) => [
                'id' => $s->id,
                'name' => $s->getTranslations('name'),
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $subtype = new AttackSubtype;
        $this->fill($subtype, $data);
        $subtype->save();

        return (new AttackSubtypeResource($subtype))->response()->setStatusCode(201);
    }

    public function update(Request $request, int $id)
    {
        $subtype = AttackSubtype::findOrFail($id);
        $data = $this->validateData($request);
        $this->fill($subtype, $data);
        $subtype->save();

        return new AttackSubtypeResource($subtype);
    }

    public function destroy(int $id)
    {
        AttackSubtype::findOrFail($id)->delete();

        return response()->noContent();
    }

    public function restore(int $id)
    {
        $subtype = AttackSubtype::withTrashed()->findOrFail($id);
        $subtype->restore();

        return new AttackSubtypeResource($subtype);
    }

    /** Borrado definitivo (desde la papelera). */
    public function forceDestroy(int $id)
    {
        AttackSubtype::withTrashed()->findOrFail($id)->forceDelete();

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

    protected function fill(AttackSubtype $subtype, array $data): void
    {
        $subtype->replaceTranslations('name', array_filter($data['name'] ?? [], fn ($v) => $v !== null && $v !== ''));
    }
}
