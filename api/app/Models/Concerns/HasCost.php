<?php

namespace App\Models\Concerns;

/**
 * Coste en dados (R/G/B) para cartas y habilidades. Columna string `cost`
 * (p. ej. "RRG"), máximo 5 dados. Se normaliza al guardar: mayúsculas, solo
 * R/G/B y orden R→G→B. Portado (simplificado) del HasCostAttribute del viejo.
 */
trait HasCost
{
    /** Máximo de dados por coste. */
    public const COST_MAX = 5;

    /** Reglas de validación del campo cost (añade 'required' donde toque). */
    public static function costRules(): array
    {
        return ['nullable', 'string', 'max:'.self::COST_MAX, 'regex:/^[RGBrgb]*$/'];
    }

    /** Normaliza: mayúsculas, descarta lo que no sea R/G/B, orden R→G→B, máx. 5. */
    public static function normalizeCost(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $counts = ['R' => 0, 'G' => 0, 'B' => 0];
        foreach (str_split(strtoupper($value)) as $die) {
            if (isset($counts[$die])) {
                $counts[$die]++;
            }
        }

        $cost = str_repeat('R', $counts['R'])
            .str_repeat('G', $counts['G'])
            .str_repeat('B', $counts['B']);

        return $cost === '' ? null : substr($cost, 0, self::COST_MAX);
    }

    /** Mutator: la columna siempre guarda el coste normalizado. */
    public function setCostAttribute(?string $value): void
    {
        $this->attributes['cost'] = self::normalizeCost($value);
    }

    /** Total de dados. */
    public function getCostTotalAttribute(): int
    {
        return strlen((string) ($this->attributes['cost'] ?? ''));
    }

    /** Colores presentes, en orden R→G→B (p. ej. ['R', 'B']). */
    public function getCostColorsAttribute(): array
    {
        $cost = (string) ($this->attributes['cost'] ?? '');

        return array_values(array_filter(
            ['R', 'G', 'B'],
            fn (string $color) => str_contains($cost, $color),
        ));
    }

    /** Clave de ordenación estable por conteos R/G/B ("020100", …). */
    public function getCostOrderAttribute(): string
    {
        $cost = (string) ($this->attributes['cost'] ?? '');

        return sprintf(
            '%02d%02d%02d',
            substr_count($cost, 'R'),
            substr_count($cost, 'G'),
            substr_count($cost, 'B'),
        );
    }

    /** Dados uno a uno para el render: [['color' => 'red', 'letter' => 'R'], …]. */
    public function getParsedCostAttribute(): array
    {
        $names = ['R' => 'red', 'G' => 'green', 'B' => 'blue'];
        $cost = (string) ($this->attributes['cost'] ?? '');

        return $cost === '' ? [] : array_map(
            fn (string $die) => ['color' => $names[$die], 'letter' => $die],
            str_split($cost),
        );
    }
}
