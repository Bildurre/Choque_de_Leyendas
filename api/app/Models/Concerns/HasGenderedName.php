<?php

namespace App\Models\Concerns;

/**
 * Nombre con género de una taxonomía de héroe (clase, superclase, raza).
 * Regla única para todo contexto de héroe (preview, ficha pública, admin):
 * héroe femenino + `name_female` con traducción en el locale pedido →
 * `name_female`; en cualquier otro caso → `name`. Los idiomas sin género
 * gramatical (en, eu) no rellenan `name_female` y caen al nombre normal.
 * En choque esto era un lang file hardcodeado (genderized.php, solo es);
 * aquí es un campo editable por taxonomía.
 */
trait HasGenderedName
{
    /** Nombre a mostrar junto a un héroe del género dado, ya localizado. */
    public function nameForGender(?string $gender, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        if ($gender === 'female') {
            // Sin fallback entre locales: el femenino solo aplica si existe
            // EN ESE idioma (en otro caso, mejor el nombre normal que un
            // femenino en un idioma equivocado).
            $female = $this->getTranslation('name_female', $locale, false);

            if ($female !== '' && $female !== null) {
                return $female;
            }
        }

        return $this->getTranslation('name', $locale);
    }

    /** Mapa locale => nombre resuelto (para los Resources del admin). */
    public function namesForGender(?string $gender): array
    {
        $names = [];
        foreach (array_keys(config('motor.locales', [])) as $locale) {
            $names[$locale] = $this->nameForGender($gender, $locale);
        }

        return $names;
    }
}
