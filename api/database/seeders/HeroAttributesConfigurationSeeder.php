<?php

namespace Database\Seeders;

use App\Models\HeroAttributesConfiguration;
use Illuminate\Database\Seeder;

/** Crea la fila única de configuración de atributos (con sus defaults). */
class HeroAttributesConfigurationSeeder extends Seeder
{
    public function run(): void
    {
        HeroAttributesConfiguration::getDefault();
    }
}
