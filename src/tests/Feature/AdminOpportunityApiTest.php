<?php

namespace Tests\Feature;

use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOpportunityApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_opportunity_with_organizations_and_tags(): void
    {
        $admin = $this->createAdminUser();
        $organizations = Organization::factory()->count(2)->create();

        $payload = [
            'name' => 'Community Cleanup',
            'description' => 'Neighborhood cleanup event.',
            'organization_ids' => $organizations->pluck('id')->toArray(),
            'tag_names' => ['Environment', 'Community'],
        ];

        $response = $this->actingAs($admin)->postJson('/admin/opportunities', $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('opportunities', ['name' => 'Community Cleanup']);

        $opportunity = Opportunity::where('name', 'Community Cleanup')->firstOrFail();
        $this->assertEqualsCanonicalizing(
            $organizations->pluck('id')->toArray(),
            $opportunity->organizations()->pluck('organizations.id')->toArray()
        );
        $this->assertEqualsCanonicalizing(
            ['Environment', 'Community'],
            $opportunity->tags()->pluck('name')->toArray()
        );
    }

    public function test_admin_can_update_opportunity_and_sync_organizations(): void
    {
        $admin = $this->createAdminUser();
        $opportunity = Opportunity::factory()->create(['name' => 'Original']);
        $existingOrg = Organization::factory()->create();
        $opportunity->organizations()->sync($existingOrg->id);

        $newOrganizations = Organization::factory()->count(2)->create();

        $payload = [
            'name' => 'Updated Opportunity',
            'organization_ids' => $newOrganizations->pluck('id')->toArray(),
        ];

        $response = $this->actingAs($admin)->patchJson("/admin/opportunities/{$opportunity->id}", $payload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('opportunities', ['id' => $opportunity->id, 'name' => 'Updated Opportunity']);
        $this->assertEqualsCanonicalizing(
            $newOrganizations->pluck('id')->toArray(),
            $opportunity->fresh()->organizations->pluck('id')->toArray()
        );
    }

    public function test_admin_can_attach_and_detach_organization(): void
    {
        $admin = $this->createAdminUser();
        $opportunity = Opportunity::factory()->create();
        $organization = Organization::factory()->create();

        $attachResponse = $this->actingAs($admin)
            ->postJson("/admin/opportunities/{$opportunity->id}/organizations/{$organization->id}");

        $attachResponse->assertStatus(200);
        $this->assertTrue($opportunity->fresh()->organizations->contains($organization->id));

        $detachResponse = $this->actingAs($admin)
            ->deleteJson("/admin/opportunities/{$opportunity->id}/organizations/{$organization->id}");

        $detachResponse->assertStatus(200);
        $this->assertFalse($opportunity->fresh()->organizations->contains($organization->id));
    }

    public function test_org_manager_must_assign_owned_organization_on_create(): void
    {
        $manager = $this->createOrganizationManager();
        $ownedOrganization = Organization::factory()->create();
        $manager->organizations()->sync($ownedOrganization->id);

        $response = $this->actingAs($manager)->postJson('/admin/opportunities', [
            'name' => 'Missing Org',
            'description' => 'Manager must select org',
        ]);

        $response->assertStatus(403);

        $unmanagedOrganization = Organization::factory()->create();
        $response = $this->actingAs($manager)->postJson('/admin/opportunities', [
            'name' => 'Wrong Org',
            'organization_ids' => [$unmanagedOrganization->id],
        ]);

        $response->assertStatus(403);

        $response = $this->actingAs($manager)->postJson('/admin/opportunities', [
            'name' => 'Allowed Org',
            'organization_ids' => [$ownedOrganization->id],
        ]);

        $response->assertStatus(200);
    }

    public function test_org_manager_can_attach_unassigned_opportunity_to_managed_org(): void
    {
        $manager = $this->createOrganizationManager();
        $organization = Organization::factory()->create();
        $manager->organizations()->sync($organization->id);
        $opportunity = Opportunity::factory()->create();

        $response = $this->actingAs($manager)
            ->postJson("/admin/opportunities/{$opportunity->id}/organizations/{$organization->id}");

        $response->assertStatus(200);
        $this->assertTrue($opportunity->fresh()->organizations->contains($organization->id));
    }

    public function test_admin_can_add_and_remove_tags(): void
    {
        $admin = $this->createAdminUser();
        $opportunity = Opportunity::factory()->create();

        $addResponse = $this->actingAs($admin)
            ->postJson("/admin/opportunities/{$opportunity->id}/tags", ['tag_name' => 'Urgent']);

        $addResponse->assertStatus(200);
        $this->assertTrue($opportunity->fresh()->tags->pluck('name')->contains('Urgent'));

        $removeResponse = $this->actingAs($admin)
            ->deleteJson("/admin/opportunities/{$opportunity->id}/tags", ['tag_name' => 'Urgent']);

        $removeResponse->assertStatus(200);
        $this->assertFalse($opportunity->fresh()->tags->pluck('name')->contains('Urgent'));
    }

    private function createAdminUser(): User
    {
        $adminRole = Role::firstOrCreate(['name' => Role::ADMIN]);
        $admin = User::factory()->create();
        $admin->roles()->syncWithoutDetaching([$adminRole->id]);

        return $admin;
    }

    private function createOrganizationManager(): User
    {
        $managerRole = Role::firstOrCreate(['name' => Role::ORGANIZATION_MANAGER]);
        $manager = User::factory()->create();
        $manager->roles()->syncWithoutDetaching([$managerRole->id]);

        return $manager;
    }
}
