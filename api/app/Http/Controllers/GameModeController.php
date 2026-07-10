<?php

namespace App\Http\Controllers;

use App\Http\Resources\GameModeResource;
use App\Models\GameMode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/** CRUD de admin para los modos de juego (taxonomía sin slug: por id). */
class GameModeController extends Controller
{
    public function index(Request $request)
    {
        $modes = GameMode::query()
            ->filter($request->only('search', 'status'))
            ->orderByDesc('id')
            ->paginate(15);

        return GameModeResource::collection($modes);
    }

    /** Lista ligera (id + nombre traducible) para selectores. */
    public function options()
    {
        return response()->json([
            'data' => GameMode::orderByDesc('id')->get()->map(fn (GameMode $mode) => [
                'id' => $mode->id,
                'name' => $mode->getTranslations('name'),
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $mode = new GameMode;
        $this->fill($mode, $data);
        $mode->save();

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
        $this->fill($mode, $data);
        $mode->save();

        return new GameModeResource($mode);
    }

    public function destroy(int $id)
    {
        GameMode::findOrFail($id)->delete();

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
        GameMode::withTrashed()->findOrFail($id)->forceDelete();

        return response()->noContent();
    }

    /** Valida nombre (default locale required) y descripción por locale. */
    protected function validateData(Request $request): array
    {
        $default = config('motor.default_locale');
        $rules = [];
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
    }
}
