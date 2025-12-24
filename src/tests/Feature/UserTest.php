<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_user_via_api(): void
    {
        $admin = User::factory()->create();

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
    }

    public function test_can_show_user_with_organizations(): void
    {
        $admin = User::factory()->create();
        $user = User::factory()->create();
        $organizations = Organization::factory()->count(2)->create();
        $user->organizations()->sync($organizations->pluck('id'));

        $response = $this->actingAs($admin)->getJson("/admin/users/{$user->id}");

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data.organizations'));
    }

    public function test_can_update_user_and_sync_organizations(): void
    {
        $admin = User::factory()->create();
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
    }

    public function test_can_delete_user(): void
    {
        $admin = User::factory()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($admin)->deleteJson("/admin/users/{$user->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_can_attach_and_detach_organization(): void
    {
        $admin = User::factory()->create();
        $user = User::factory()->create();
        $organization = Organization::factory()->create();

        $attachResponse = $this->actingAs($admin)->postJson("/admin/users/{$user->id}/organizations/{$organization->id}");
        $attachResponse->assertStatus(200);
        $this->assertTrue($user->fresh()->organizations->contains($organization->id));

        $detachResponse = $this->actingAs($admin)->deleteJson("/admin/users/{$user->id}/organizations/{$organization->id}");
        $detachResponse->assertStatus(200);
        $this->assertFalse($user->fresh()->organizations->contains($organization->id));
    }
}
