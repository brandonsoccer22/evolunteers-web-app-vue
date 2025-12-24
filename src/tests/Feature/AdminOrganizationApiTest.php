<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrganizationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_organization_with_users(): void
    {
        $admin = User::factory()->create();
        $users = User::factory()->count(2)->create();

        $payload = [
            'name' => 'New Org',
            'description' => 'Test description',
            'user_ids' => $users->pluck('id')->toArray(),
        ];

        $response = $this->actingAs($admin)->postJson('/admin/organizations', $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('organizations', ['name' => 'New Org', 'description' => 'Test description']);
        $this->assertEqualsCanonicalizing(
            $users->pluck('id')->toArray(),
            Organization::where('name', 'New Org')->first()->users->pluck('id')->toArray()
        );
    }

    public function test_admin_can_show_organization_with_users(): void
    {
        $admin = User::factory()->create();
        $organization = Organization::factory()->create();
        $users = User::factory()->count(2)->create();
        $organization->users()->sync($users->pluck('id')->toArray());

        $response = $this->actingAs($admin)->getJson("/admin/organizations/{$organization->id}");

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data.users'));
    }

    public function test_admin_can_update_organization_and_sync_users(): void
    {
        $admin = User::factory()->create();
        $organization = Organization::factory()->create(['name' => 'Original']);
        $existingUser = User::factory()->create();
        $organization->users()->sync($existingUser->id);

        $newUsers = User::factory()->count(2)->create();

        $payload = [
            'name' => 'Updated Org',
            'description' => 'Updated description',
            'user_ids' => $newUsers->pluck('id')->toArray(),
        ];

        $response = $this->actingAs($admin)->patchJson("/admin/organizations/{$organization->id}", $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('organizations', ['id' => $organization->id, 'name' => 'Updated Org']);
        $this->assertEqualsCanonicalizing(
            $newUsers->pluck('id')->toArray(),
            $organization->fresh()->users->pluck('id')->toArray()
        );
    }

    public function test_admin_can_delete_organization(): void
    {
        $admin = User::factory()->create();
        $organization = Organization::factory()->create();

        $response = $this->actingAs($admin)->deleteJson("/admin/organizations/{$organization->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('organizations', ['id' => $organization->id]);
    }

    public function test_admin_can_attach_and_detach_user_via_routes(): void
    {
        $admin = User::factory()->create();
        $organization = Organization::factory()->create();
        $user = User::factory()->create();

        $attachResponse = $this->actingAs($admin)->postJson("/admin/organizations/{$organization->id}/users/{$user->id}");
        $attachResponse->assertStatus(200);
        $this->assertTrue($organization->fresh()->users->contains($user->id));

        $detachResponse = $this->actingAs($admin)->deleteJson("/admin/organizations/{$organization->id}/users/{$user->id}");
        $detachResponse->assertStatus(200);
        $this->assertFalse($organization->fresh()->users->contains($user->id));
    }
}
