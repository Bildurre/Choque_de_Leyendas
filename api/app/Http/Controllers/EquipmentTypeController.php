<?php

namespace App\Http\Controllers;

use App\Http\Resources\EquipmentTypeResource;
use App\Models\EquipmentType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** CRUD de admin para los tipos de equipo (taxonomía sin slug: por id). */
class EquipmentTypeController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');

        $types = EquipmentType::query()
            ->filter($request->only('search', 'status'))
            // El filtro por categoría del listado viaja como tab en `status`
            // (weapon|armor); HasFilters ignora esos valores.
            ->when(
                in_array($status, EquipmentType::CATEGORIES, true),
                fn ($query) => $query->where('category', $status),
            )
            ->orderByDesc('id')
            ->paginate(15);

        return EquipmentTypeResource::collection($types);
    }

    /** Lista ligera para selectores; incluye la categoría (weapon|armor). */
    public function options()
    {
        return response()->json([
            'data' => EquipmentType::orderByDesc('id')->get()->map(fn (EquipmentType $type) => [
                'id' => $type->id,
                'name' => $type->getTranslations('name'),
                'category' => $type->category,
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $type = new EquipmentType;
        $this->fill($type, $data);
        $type->save();

        return (new EquipmentTypeResource($type))->response()->setStatusCode(201);
    }

    public function show(int $id)
    {
        return new EquipmentTypeResource(EquipmentType::findOrFail($id));
    }

    public function update(Request $request, int $id)
    {
        $type = EquipmentType::findOrFail($id);
        $data = $this->validateData($request);
        $this->fill($type, $data);
        $type->save();

        return new EquipmentTypeResource($type);
    }

    public function destroy(int $id)
    {
        EquipmentType::findOrFail($id)->delete();

        return response()->noContent();
    }

    public function restore(int $id)
    {
        $type = EquipmentType::withTrashed()->findOrFail($id);
        $type->restore();

        return new EquipmentTypeResource($type);
    }

    /** Borrado definitivo (desde la papelera). */
    public function forceDestroy(int $id)
    {
        EquipmentType::withTrashed()->findOrFail($id)->forceDelete();

        return response()->noContent();
    }

    /** Valida el nombre por locale (default required) + categoría. */
    protected function validateData(Request $request): array
    {
        $default = config('motor.default_locale');
        $rules = [
            'category' => ['required', 'string', Rule::in(EquipmentType::CATEGORIES)],
        ];
        foreach (array_keys(config('motor.locales', [])) as $locale) {
            $rules["name.$locale"] = [$locale === $default ? 'required' : 'nullable', 'string', 'max:255'];
        }

        return Validator::make($request->all(), $rules)->validate();
    }

    protected function fill(EquipmentType $type, array $data): void
    {
        $type->replaceTranslations('name', array_filter($data['name'] ?? [], fn ($v) => $v !== null && $v !== ''));
        $type->category = $data['category'];
    }
}
