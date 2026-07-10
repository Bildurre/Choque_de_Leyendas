<?php

namespace App\Http\Controllers;

use App\Models\HeroAttributesConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Configuración de atributos de héroe (singleton): GET devuelve la fila
 * única (creándola si falta) y PUT la actualiza. Sin Resource: la fila
 * son 10 enteros sin nada que traducir.
 */
class HeroAttributesConfigurationController extends Controller
{
    public function show()
    {
        return response()->json(['data' => HeroAttributesConfiguration::getDefault()]);
    }

    public function update(Request $request)
    {
        // Rangos de sanidad heredados del form del viejo.
        $data = Validator::make($request->all(), [
            'min_attribute_value' => ['required', 'integer', 'between:1,3'],
            'max_attribute_value' => ['required', 'integer', 'between:3,10', 'gte:min_attribute_value'],
            'min_total_attributes' => ['required', 'integer', 'between:5,20'],
            'max_total_attributes' => ['required', 'integer', 'between:10,50', 'gte:min_total_attributes'],
            'agility_multiplier' => ['required', 'integer', 'between:-5,5'],
            'mental_multiplier' => ['required', 'integer', 'between:-5,5'],
            'will_multiplier' => ['required', 'integer', 'between:-5,5'],
            'strength_multiplier' => ['required', 'integer', 'between:-5,5'],
            'armor_multiplier' => ['required', 'integer', 'between:-5,5'],
            'total_health_base' => ['required', 'integer', 'between:10,100'],
        ])->validate();

        $config = HeroAttributesConfiguration::getDefault();
        $config->fill($data);
        $config->save();

        return response()->json(['data' => $config]);
    }
}
