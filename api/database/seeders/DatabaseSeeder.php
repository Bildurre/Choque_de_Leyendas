<?php

namespace Database\Seeders;

use App\Models\User;
use Edc\Core\Auth\MotorAuth;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Deja el juego listo para entrar: roles + un usuario de prueba por rol,
     * una home mínima en el CRM y la configuración inicial de la web.
     * Idempotente: se puede re-ejecutar sin duplicar.
     *
     * Regla de la casa: TODO lo que crees durante el desarrollo (entidades,
     * páginas, iconos…) va también a un seeder, para poder reconstruir la
     * demo con un migrate:fresh --seed.
     */
    public function run(): void
    {
        // Roles + permisos del motor con su reparto (y limpieza de la caché
        // de Spatie, que puede quedar rancia tras un migrate:fresh).
        MotorAuth::syncRolesAndPermissions();

        $usuarios = [
            ['admin', 'Admin', 'admin@edc.test'],
            ['editor', 'Editor', 'editor@edc.test'],
            ['user', 'Usuario', 'user@edc.test'],
        ];

        foreach ($usuarios as [$role, $name, $email]) {
            $user = User::firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => Hash::make('password')],
            );
            // Verificados de fábrica: son cuentas de demo, sin correo real.
            if (! $user->hasVerifiedEmail()) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }
            $user->syncRoles([$role]);
        }

        $this->call([PagesSeeder::class, SiteSettingsSeeder::class]);
    }
}
