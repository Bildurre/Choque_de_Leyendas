<?php

namespace App\Http\Controllers;

use App\Http\Resources\DeckAttributesConfigurationResource;
use App\Models\DeckAttributesConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * CRUD de admin para las configuraciones de mazo (sin slug ni publicación
 * ni papelera: por id). `forMode` sirve los límites al editor de mazos.
 */
class DeckAttributesConfigurationController extends Controller
{
    public function index(Request $request)
    {
        $configs = DeckAttributesConfiguration::query()
            ->with('gameMode')
            ->orderByDesc('id')
            ->paginate(15);

        return DeckAttributesConfigurationResource::collection($configs);
    }

    /**
     * Límites para un modo de juego (o la configuración genérica sin modo).
     * `data` puede ser null: el editor de mazos lo trata como "sin límites".
     */
    public function forMode(int $gameMode)
    {
        $config = DeckAttributesConfiguration::forMode($gameMode);

        return response()->json([
            'data' => $config ? new DeckAttributesConfigurationResource($config->loadMissing('gameMode')) : null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $config = DeckAttributesConfiguration::create($data);

        return (new DeckAttributesConfigurationResource($config->load('gameMode')))
            ->response()->setStatusCode(201);
    }

    public function show(int $id)
    {
        return new DeckAttributesConfigurationResource(
            DeckAttributesConfiguration::with('gameMode')->findOrFail($id),
        );
    }

    public function update(Request $request, int $id)
    {
        $config = DeckAttributesConfiguration::findOrFail($id);
        $config->fill($this->validateData($request));
        $config->save();

        return new DeckAttributesConfigurationResource($config->load('gameMode'));
    }

    public function destroy(int $id)
    {
        DeckAttributesConfiguration::findOrFail($id)->delete();

        return response()->noContent();
    }

    /** Rangos de sanidad heredados del form del viejo. */
    protected function validateData(Request $request): array
    {
        return Validator::make($request->all(), [
            'game_mode_id' => ['nullable', 'integer', 'exists:game_modes,id'],
            'min_cards' => ['required', 'integer', 'between:1,200'],
            'max_cards' => ['required', 'integer', 'between:1,200', 'gte:min_cards'],
            'max_copies_per_card' => ['required', 'integer', 'between:1,20'],
            'required_heroes' => ['required', 'integer', 'between:0,20'],
        ])->validate();
    }
}
