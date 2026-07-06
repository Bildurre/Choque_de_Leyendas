<?php

use App\Models\Character;
use App\Models\User;
use Edc\Core\Auth\MotorAuth;
use Edc\Core\Previews\PreviewRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * Crea (si no existen) los roles base del motor y devuelve un usuario con el
 * rol pedido. Equivale a lo que hace `motor:install` en una instalación real.
 */
/**
 * Renderer de mentira: no abre Chromium; escribe un PNG real de 1x1 (DomPDF
 * necesita imágenes válidas) y registra las URLs capturadas.
 */
class FakePreviewRenderer extends PreviewRenderer
{
    public array $captured = [];

    public function capture(string $url, int $width, int $height, string $savePath): void
    {
        $this->captured[] = compact('url', 'width', 'height');
        file_put_contents($savePath, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        ));
    }
}

function fakeRenderer(): FakePreviewRenderer
{
    $fake = new FakePreviewRenderer;
    app()->instance(PreviewRenderer::class, $fake);

    return $fake;
}

function makeCharacter(array $overrides = []): Character
{
    $character = new Character;
    $character->setTranslations('name', $overrides['name'] ?? ['es' => 'Tyrion']);
    $character->power = $overrides['power'] ?? 1;
    $character->prestige = 2;
    $character->intrigue = 3;
    $character->money = 4;
    $character->is_published = $overrides['is_published'] ?? false;
    $character->save();

    return $character;
}

function motorUser(string $role = 'user'): User
{
    // Roles + permisos con su reparto (los del config del motor).
    MotorAuth::syncRolesAndPermissions();

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}
