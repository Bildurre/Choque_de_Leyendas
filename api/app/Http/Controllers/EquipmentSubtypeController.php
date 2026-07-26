<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersByIds;
use App\Http\Controllers\Concerns\SortsIndex;
use App\Http\Resources\EquipmentSubtypeResource;
use App\Models\EquipmentSubtype;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/** CRUD de admin para los subtipos de equipo (taxonomía sin slug: por id). */
class EquipmentSubtypeController extends Controller
{
    use FiltersByIds;
    use SortsIndex;

    public function index(Request $request)
    {
        // Filtro multivalor (escalar o array, contrato de FiltersByIds).
        $typeIds = $this->idsFrom($request, 'equipment_type_id');

        $subtypes = EquipmentSubtype::query()
            ->with('equipmentType')
            ->filter($request->only('search', 'status'))
            // Filtro del listado (multiselect en el panel derecho).
            ->when($typeIds !== [], fn ($query) => $query->whereIn('equipment_type_id', $typeIds))
            ->tap(fn ($query) => $this->applySort($query, $request->query('sort')))
            ->paginate(15);

        return EquipmentSubtypeResource::collection($subtypes);
    }

    /** Lista ligera para selectores; con el tipo al que pertenece. */
    public function options()
    {
        return response()->json([
            'data' => $this->orderByName(EquipmentSubtype::query())->get()->map(fn (EquipmentSubtype $subtype) => [
                'id' => $subtype->id,
                'name' => $subtype->getTranslations('name'),
                'equipment_type_id' => $subtype->equipment_type_id,
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $subtype = new EquipmentSubtype;
        $this->fill($subtype, $data);
        $subtype->save();

        return (new EquipmentSubtypeResource($subtype->load('equipmentType')))->response()->setStatusCode(201);
    }

    public function show(int $id)
    {
        return new EquipmentSubtypeResource(EquipmentSubtype::with('equipmentType')->findOrFail($id));
    }

    public function update(Request $request, int $id)
    {
        $subtype = EquipmentSubtype::findOrFail($id);
        $data = $this->validateData($request);
        $this->fill($subtype, $data);
        $subtype->save();

        return new EquipmentSubtypeResource($subtype->load('equipmentType'));
    }

    public function destroy(int $id)
    {
        EquipmentSubtype::findOrFail($id)->delete();

        return response()->noContent();
    }

    public function restore(int $id)
    {
        $subtype = EquipmentSubtype::withTrashed()->findOrFail($id);
        $subtype->restore();

        return new EquipmentSubtypeResource($subtype->load('equipmentType'));
    }

    /** Borrado definitivo (desde la papelera). */
    public function forceDestroy(int $id)
    {
        EquipmentSubtype::withTrashed()->findOrFail($id)->forceDelete();

        return response()->noContent();
    }

    /** Valida el nombre por locale (default required) + tipo obligatorio. */
    protected function validateData(Request $request): array
    {
        $default = config('motor.default_locale');
        $rules = [
            'equipment_type_id' => ['required', 'integer', 'exists:equipment_types,id'],
        ];
        foreach (array_keys(config('motor.locales', [])) as $locale) {
            $rules["name.$locale"] = [$locale === $default ? 'required' : 'nullable', 'string', 'max:255'];
        }

        return Validator::make($request->all(), $rules)->validate();
    }

    protected function fill(EquipmentSubtype $subtype, array $data): void
    {
        $subtype->replaceTranslations('name', array_filter($data['name'] ?? [], fn ($v) => $v !== null && $v !== ''));
        $subtype->equipment_type_id = (int) $data['equipment_type_id'];
    }
}
