<?php

namespace App\Http\Controllers;

use App\Http\Resources\CardTypeResource;
use App\Models\CardType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** CRUD de admin para los tipos de carta (taxonomía sin slug: por id). */
class CardTypeController extends Controller
{
    public function index(Request $request)
    {
        $types = CardType::query()
            ->with('heroSuperclass')
            ->filter($request->only('search', 'status'))
            ->orderByDesc('id')
            ->paginate(15);

        return CardTypeResource::collection($types);
    }

    /** Lista ligera para selectores; incluye los flags que condicionan el form de cartas. */
    public function options()
    {
        return response()->json([
            'data' => CardType::orderByDesc('id')->get()->map(fn (CardType $type) => [
                'id' => $type->id,
                'name' => $type->getTranslations('name'),
                'hero_superclass_id' => $type->hero_superclass_id,
                'allows_subtypes' => $type->allows_subtypes,
                'is_equipment' => $type->is_equipment,
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $type = new CardType;
        $this->fill($type, $data);
        $type->save();

        return (new CardTypeResource($type))->response()->setStatusCode(201);
    }

    public function show(int $id)
    {
        return new CardTypeResource(CardType::with('heroSuperclass')->findOrFail($id));
    }

    public function update(Request $request, int $id)
    {
        $type = CardType::findOrFail($id);
        $data = $this->validateData($request, $type);
        $this->fill($type, $data);
        $type->save();

        return new CardTypeResource($type->load('heroSuperclass'));
    }

    public function destroy(int $id)
    {
        CardType::findOrFail($id)->delete();

        return response()->noContent();
    }

    public function restore(int $id)
    {
        $type = CardType::withTrashed()->findOrFail($id);
        $type->restore();

        return new CardTypeResource($type);
    }

    /** Borrado definitivo (desde la papelera). */
    public function forceDestroy(int $id)
    {
        CardType::withTrashed()->findOrFail($id)->forceDelete();

        return response()->noContent();
    }

    /** Valida el nombre por locale (default required) + superclase única + flags. */
    protected function validateData(Request $request, ?CardType $current = null): array
    {
        $default = config('motor.default_locale');
        $rules = [
            'hero_superclass_id' => [
                'nullable',
                'integer',
                'exists:hero_superclasses,id',
                Rule::unique('card_types', 'hero_superclass_id')->ignore($current?->id),
            ],
            'allows_subtypes' => ['boolean'],
            'is_equipment' => ['boolean'],
        ];
        foreach (array_keys(config('motor.locales', [])) as $locale) {
            $rules["name.$locale"] = [$locale === $default ? 'required' : 'nullable', 'string', 'max:255'];
        }

        return Validator::make($request->all(), $rules)->validate();
    }

    protected function fill(CardType $type, array $data): void
    {
        $type->replaceTranslations('name', array_filter($data['name'] ?? [], fn ($v) => $v !== null && $v !== ''));
        $type->hero_superclass_id = $data['hero_superclass_id'] ?? null;
        $type->allows_subtypes = (bool) ($data['allows_subtypes'] ?? false);
        $type->is_equipment = (bool) ($data['is_equipment'] ?? false);
    }
}
