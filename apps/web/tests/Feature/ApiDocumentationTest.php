<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

test('only the main administrator can open api documentation', function () {
    $employee = User::factory()->create();
    $admin = User::factory()->create();
    $admin->forceFill(['is_super_admin' => true])->save();

    $this->actingAs($employee)
        ->get(route('api-docs.ui'))
        ->assertForbidden();

    $this->actingAs($admin)
        ->get(route('api-docs.ui'))
        ->assertOk()
        ->assertSee('id="app"', escape: false);
});

test('openapi specification is valid json and contains every application api route', function () {
    $admin = User::factory()->create();
    $admin->forceFill(['is_super_admin' => true])->save();

    $response = $this->actingAs($admin)
        ->getJson(route('api-docs.spec'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.oai.openapi+json')
        ->assertJsonPath('openapi', '3.1.0');

    /** @var array{paths: array<string, array<string, mixed>>} $specification */
    $specification = json_decode(
        file_get_contents(resource_path('openapi/openapi.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($response->json())->toBe($specification);

    foreach (Route::getRoutes() as $route) {
        if (! str_starts_with($route->uri(), 'api/')) {
            continue;
        }

        $path = '/'.$route->uri();

        foreach (array_diff($route->methods(), ['HEAD']) as $method) {
            expect($specification['paths'][$path][strtolower($method)] ?? null)
                ->not->toBeNull("Missing OpenAPI operation for {$method} {$path}");
        }
    }
});
