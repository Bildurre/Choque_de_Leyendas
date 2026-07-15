<?php

namespace App\Http\Controllers;

use App\Http\Resources\LifeCounterMatchResource;
use App\Models\LifeCounterMatch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Histórico del contador de vidas (herramienta de la web pública, doc 10):
 * partidas del usuario autenticado — auth:sanctum, sin rol de admin. El
 * estado es un json opaco del cliente (equipos: facciones + héroes con sus
 * vidas); aquí solo se valida la forma mínima y la propiedad: nadie ve ni
 * toca partidas ajenas (404 vía scoping por user_id).
 */
class LifeCounterMatchController extends Controller
{
    /** Partidas del usuario, las más recientes primero (activas incluidas). */
    public function index(Request $request)
    {
        $matches = $this->owned($request)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return LifeCounterMatchResource::collection($matches);
    }

    /** Crea una partida activa con el estado inicial (al "Comenzar"). */
    public function store(Request $request)
    {
        $data = $request->validate(['state' => ['required', 'array']]);

        $match = LifeCounterMatch::create([
            'user_id' => $request->user()->id,
            'state' => $data['state'],
            'status' => 'active',
        ]);

        return (new LifeCounterMatchResource($match))->response()->setStatusCode(201);
    }

    /**
     * Actualiza el estado de una partida ACTIVA propia (el cliente lo envía
     * debounceado al cambiar vidas). Las terminadas son solo lectura: 404.
     */
    public function update(Request $request, int $id)
    {
        $match = $this->owned($request)->where('status', 'active')->findOrFail($id);
        $data = $request->validate(['state' => ['required', 'array']]);

        $match->update(['state' => $data['state']]);

        return new LifeCounterMatchResource($match);
    }

    /** Termina una partida propia (estado final opcional en el body). */
    public function finish(Request $request, int $id)
    {
        $match = $this->owned($request)->findOrFail($id);
        $data = $request->validate(['state' => ['sometimes', 'array']]);

        $match->update(array_merge(['status' => 'finished'], $data));

        return new LifeCounterMatchResource($match);
    }

    /** Query base: SOLO las partidas del usuario autenticado. */
    private function owned(Request $request): Builder
    {
        return LifeCounterMatch::where('user_id', $request->user()->id);
    }
}
