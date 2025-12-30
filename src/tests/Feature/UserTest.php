<?php

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('can create user via api', function (): void {
    $admin = createAdminUser();

    $payload = [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john.doe@example.com',
        'password' => 'password123',
    ];

    $response = $this->actingAs($admin)->postJson('/admin/users', $payload);

    $response->assertStatus(200);
    $this->assertDatabaseHas('users', [
        'email' => 'john.doe@example.com',
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);
});

test('can show user with organizations', function (): void {
    $admin = createAdminUser();
    $user = User::factory()->create();
    $organizations = Organization::factory()->count(2)->create();
    $user->organizations()->sync($organizations->pluck('id'));

    $response = $this->actingAs($admin)->getJson("/admin/users/{$user->id}");

    $response->assertStatus(200);
    $this->assertCount(2, $response->json('data.organizations'));
});

test('can update user and sync organizations', function (): void {
    $admin = createAdminUser();
    $user = User::factory()->create();
    $existingOrg = Organization::factory()->create();
    $user->organizations()->sync($existingOrg->id);

    $newOrgs = Organization::factory()->count(2)->create();

    $payload = [
        'first_name' => 'Updated',
        'last_name' => 'User',
        'email' => 'updated@example.com',
        'organization_ids' => $newOrgs->pluck('id')->toArray(),
    ];

    $response = $this->actingAs($admin)->patchJson("/admin/users/{$user->id}", $payload);

    $response->assertStatus(200);
    $this->assertDatabaseHas('users', ['email' => 'updated@example.com']);
    $this->assertEqualsCanonicalizing(
        $newOrgs->pluck('id')->toArray(),
        $user->fresh()->organizations->pluck('id')->toArray()
    );
});

test('can delete user', function (): void {
    $admin = createAdminUser();
    $user = User::factory()->create();

    $response = $this->actingAs($admin)->deleteJson("/admin/users/{$user->id}");

    $response->assertStatus(200);
    $this->assertSoftDeleted('users', ['id' => $user->id]);
});

test('can attach and detach organization', function (): void {
    $admin = createAdminUser();
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    $attachResponse = $this->actingAs($admin)->postJson("/admin/users/{$user->id}/organizations/{$organization->id}");
    $attachResponse->assertStatus(200);
    $this->assertTrue($user->fresh()->organizations->contains($organization->id));

    $detachResponse = $this->actingAs($admin)->deleteJson("/admin/users/{$user->id}/organizations/{$organization->id}");
    $detachResponse->assertStatus(200);
    $this->assertFalse($user->fresh()->organizations->contains($organization->id));
});
