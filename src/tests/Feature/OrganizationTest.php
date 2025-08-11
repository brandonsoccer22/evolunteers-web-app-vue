<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Organization;
use App\Models\Opportunity;
use App\Models\Tag;
use App\Models\File;
use Illuminate\Foundation\Testing\RefreshDatabase;

class OrganizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_organization()
    {
        $org = Organization::factory()->create(['name' => 'Test Org']);
        $this->assertDatabaseHas('organizations', ['name' => 'Test Org']);
    }

    public function test_can_read_organization()
    {
        $org = Organization::factory()->create();
        $found = Organization::find($org->id);
        $this->assertNotNull($found);
        $this->assertEquals($org->id, $found->id);
    }

    public function test_can_update_organization()
    {
        $org = Organization::factory()->create();
        $org->update(['name' => 'Updated Org']);
        $this->assertDatabaseHas('organizations', ['name' => 'Updated Org']);
    }

    public function test_can_soft_delete_organization()
    {
        $org = Organization::factory()->create();
        $org->delete();
        $this->assertSoftDeleted($org);
    }

    public function test_organization_can_create_opportunity()
    {
        $org = Organization::factory()->create();
        $opp = Opportunity::factory()->create();
        $org->opportunities()->attach($opp);
        $this->assertTrue($org->opportunities->contains($opp));
    }

    public function test_organization_can_be_tagged()
    {
        $org = Organization::factory()->create();
        $tag = Tag::factory()->create();
        $org->tags()->attach($tag);
        $this->assertTrue($org->tags->contains($tag));
    }

    public function test_organization_can_have_files()
    {
        $org = Organization::factory()->create();
        $file = File::factory()->create();
        $org->files()->attach($file);
        $this->assertTrue($org->files->contains($file));
    }

    public function test_organization_can_sync_tags()
    {
        $org = Organization::factory()->create();
        $tags = Tag::factory()->count(3)->create();
        $org->tags()->sync($tags->pluck('id')->toArray());
        $this->assertCount(3, $org->tags);
    }

    public function test_organization_can_detach_a_tag()
    {
        $org = Organization::factory()->create();
        $tag = Tag::factory()->create();
        $org->attachTag($tag);
        $org->tags()->detach($tag);
        $org->refresh();
        $this->assertFalse($org->tags->contains($tag));
    }

    public function test_organization_files_are_deleted_when_organization_is_deleted()
    {
        $org = Organization::factory()->create();
        $file = File::factory()->create();
        $org->files()->attach($file);
        $org->delete();
        // The file record may still exist, but the pivot should be gone
        $this->assertDatabaseMissing('fileables', [
            'fileable_id' => $org->id,
            'fileable_type' => Organization::class,
            'file_id' => $file->id,
        ]);
    }

    public function test_created_by_is_set_on_create()
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $org = Organization::factory()->create();

        $this->assertEquals($user->id, $org->created_by);
    }

    public function test_updated_by_is_set_on_update()
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $org = Organization::factory()->create();
        $org->update(['name' => 'Changed Name']);

        $org->refresh();
        $this->assertEquals($user->id, $org->updated_by);
    }

    public function test_deleted_by_is_set_on_delete()
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $org = Organization::factory()->create();
        $org->delete();

        $org = Organization::withTrashed()->find($org->id);
        $this->assertEquals($user->id, $org->deleted_by);
    }
}
