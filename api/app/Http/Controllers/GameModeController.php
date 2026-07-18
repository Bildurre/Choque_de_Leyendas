<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SortsIndex;
use App\Http\Resources\GameModeResource;
use App\Models\GameMode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * CRUD de admin para los modos de juego (taxonomía sin slug: por id), con su
 * configuración de mazos integrada y el flag is_default: siempre hay
 * exactamente un modo por defecto (marcar uno desmarca el anterior; el por
 * defecto no se puede desmarcar ni borrar directamente).
 */
class GameModeController extends Controller
{
    use SortsIndex;

    public function index(Request $request)
    {
        $modes = GameMode::query()
            ->filter($request->only('search', 'status'))
            ->tap(fn ($query) => $this->applySort($query, $request->query('sort')))
            ->paginate(15);

        return GameModeResource::collection($modes);
    }

    /** Lista ligera (id + nombre traducible + is_default) para selectores. */
    public function options()
    {
        return response()->json([
            'data' => GameMode::orderByDesc('id')->get()->map(fn (GameMode $mode) => [
                'id' => $mode->id,
                'name' => $mode->getTranslations('name'),
                'is_default' => $mode->is_default,
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $mode = new GameMode;
        $this->fill($mode, $data);
        $this->saveWithDefaultFlag($mode, $data);

        return (new GameModeResource($mode))->response()->setStatusCode(201);
    }

    public function show(int $id)
    {
        return new GameModeResource(GameMode::findOrFail($id));
    }

    public function update(Request $request, int $id)
    {
        $mode = GameMode::findOrFail($id);
        $data = $this->validateData($request);

        // Exactamente un por defecto: el actual no se desmarca a sí mismo
        // (hay que marcar otro modo, que lo desmarcará en la transacción).
        if ($mode->is_default && array_key_exists('is_default', $data) && ! $data['is_default']) {
            throw ValidationException::withMessages([
                'is_default' => ['Siempre debe haber un modo por defecto: marca otro modo para sustituirlo.'],
            ]);
        }

        $this->fill($mode, $data);
        $this->saveWithDefaultFlag($mode, $data);

        return new GameModeResource($mode);
    }

    public function destroy(int $id)
    {
        $mode = GameMode::findOrFail($id);
        $this->rejectIfDefault($mode);
        $mode->delete();

        return response()->noContent();
    }

    public function restore(int $id)
    {
        $mode = GameMode::withTrashed()->findOrFail($id);
        $mode->restore();

        return new GameModeResource($mode);
    }

    /** Borrado definitivo (desde la papelera). */
    public function forceDestroy(int $id)
    {
        $mode = GameMode::withTrashed()->findOrFail($id);
        $this->rejectIfDefault($mode);
        $mode->forceDelete();

        return response()->noContent();
    }

    /** El modo por defecto no se borra: primero hay que marcar otro. */
    protected function rejectIfDefault(GameMode $mode): void
    {
        if ($mode->is_default) {
            throw ValidationException::withMessages([
                'is_default' => ['El modo por defecto no se puede eliminar: marca antes otro modo como por defecto.'],
            ]);
        }
    }

    /**
     * Guarda dentro de una transacción manteniendo el invariante "exactamente
     * un por defecto": marcar este desmarca el anterior, y si no queda
     * ninguno (primer modo creado) este pasa a serlo.
     */
    protected function saveWithDefaultFlag(GameMode $mode, array $data): void
    {
        DB::transaction(function () use ($mode, $data) {
            if (! empty($data['is_default'])) {
                $mode->is_default = true;
                GameMode::withTrashed()
                    ->where('is_default', true)
                    ->when($mode->id, fn ($q) => $q->where('id', '!=', $mode->id))
                    ->update(['is_default' => false]);
            }

            $mode->save();

            if (! GameMode::where('is_default', true)->exists()) {
                $mode->is_default = true;
                $mode->save();
            }
        });
    }

    /** Nombre/descripción por locale + límites de mazo + flag por defecto. */
    protected function validateData(Request $request): array
    {
        $default = config('motor.default_locale');
        $rules = [
            'min_cards' => ['required', 'integer', 'between:1,200'],
            'max_cards' => ['required', 'integer', 'between:1,200', 'gte:min_cards'],
            'max_copies_per_card' => ['required', 'integer', 'between:1,20'],
            'required_heroes' => ['required', 'integer', 'between:0,20'],
            'is_default' => ['boolean'],
        ];
        foreach (array_keys(config('motor.locales', [])) as $locale) {
            $rules["name.$locale"] = [$locale === $default ? 'required' : 'nullable', 'string', 'max:255'];
            $rules["description.$locale"] = ['nullable', 'string'];
        }

        return Validator::make($request->all(), $rules)->validate();
    }

    protected function fill(GameMode $mode, array $data): void
    {
        $mode->replaceTranslations('name', array_filter($data['name'] ?? [], fn ($v) => $v !== null && $v !== ''));
        $mode->replaceTranslations('description', array_filter($data['description'] ?? [], fn ($v) => $v !== null && $v !== ''));
        $mode->min_cards = (int) $data['min_cards'];
        $mode->max_cards = (int) $data['max_cards'];
        $mode->max_copies_per_card = (int) $data['max_copies_per_card'];
        $mode->required_heroes = (int) $data['required_heroes'];
    }
}
