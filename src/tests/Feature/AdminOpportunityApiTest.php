<?php

use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('admin can create opportunity with organizations and tags', function (): void {
    $admin = createAdminUser();
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
});

test('admin can update opportunity and sync organizations', function (): void {
    $admin = createAdminUser();
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
});

test('admin can attach and detach organization', function (): void {
    $admin = createAdminUser();
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
});

test('org manager must assign owned organization on create', function (): void {
    $manager = createOrganizationManager();
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
});

test('org manager cannot attach organization to uneditable opportunity', function (): void {
    $manager = createOrganizationManager();
    $organization = Organization::factory()->create();
    $manager->organizations()->sync($organization->id);
    $opportunity = Opportunity::factory()->create();

    $response = $this->actingAs($manager)
        ->postJson("/admin/opportunities/{$opportunity->id}/organizations/{$organization->id}");

    $response->assertStatus(403);
    $this->assertFalse($opportunity->fresh()->organizations->contains($organization->id));
});

test('org manager can attach managed organization to editable opportunity', function (): void {
    $manager = createOrganizationManager();
    $primaryOrganization = Organization::factory()->create();
    $secondaryOrganization = Organization::factory()->create();
    $manager->organizations()->sync([$primaryOrganization->id, $secondaryOrganization->id]);

    $opportunity = Opportunity::factory()->create();
    $opportunity->organizations()->sync([$primaryOrganization->id]);

    $response = $this->actingAs($manager)
        ->postJson("/admin/opportunities/{$opportunity->id}/organizations/{$secondaryOrganization->id}");

    $response->assertStatus(200);
    $this->assertTrue($opportunity->fresh()->organizations->contains($secondaryOrganization->id));
});

test('org manager can detach managed organization from editable opportunity', function (): void {
    $manager = createOrganizationManager();
    $primaryOrganization = Organization::factory()->create();
    $secondaryOrganization = Organization::factory()->create();
    $manager->organizations()->sync([$primaryOrganization->id, $secondaryOrganization->id]);

    $opportunity = Opportunity::factory()->create();
    $opportunity->organizations()->sync([$primaryOrganization->id, $secondaryOrganization->id]);

    $response = $this->actingAs($manager)
        ->deleteJson("/admin/opportunities/{$opportunity->id}/organizations/{$secondaryOrganization->id}");

    $response->assertStatus(200);
    $this->assertFalse($opportunity->fresh()->organizations->contains($secondaryOrganization->id));
});

test('org manager cannot detach unmanaged organization from opportunity', function (): void {
    $manager = createOrganizationManager();
    $managedOrganization = Organization::factory()->create();
    $unmanagedOrganization = Organization::factory()->create();
    $manager->organizations()->sync($managedOrganization->id);

    $opportunity = Opportunity::factory()->create();
    $opportunity->organizations()->sync([$managedOrganization->id, $unmanagedOrganization->id]);

    $response = $this->actingAs($manager)
        ->deleteJson("/admin/opportunities/{$opportunity->id}/organizations/{$unmanagedOrganization->id}");

    $response->assertStatus(403);
    $this->assertTrue($opportunity->fresh()->organizations->contains($unmanagedOrganization->id));
});

test('admin can add and remove tags', function (): void {
    $admin = createAdminUser();
    $opportunity = Opportunity::factory()->create();

    $addResponse = $this->actingAs($admin)
        ->postJson("/admin/opportunities/{$opportunity->id}/tags", ['tag_name' => 'Urgent']);

    $addResponse->assertStatus(200);
    $this->assertTrue($opportunity->fresh()->tags->pluck('name')->contains('Urgent'));

    $removeResponse = $this->actingAs($admin)
        ->deleteJson("/admin/opportunities/{$opportunity->id}/tags", ['tag_name' => 'Urgent']);

    $removeResponse->assertStatus(200);
    $this->assertFalse($opportunity->fresh()->tags->pluck('name')->contains('Urgent'));
});
