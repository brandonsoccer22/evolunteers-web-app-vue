<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\Tag;
use App\Models\File;
use Illuminate\Foundation\Testing\RefreshDatabase;

class OpportunityTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_opportunity()
    {
        $opp = Opportunity::factory()->create(['name' => 'Test Opportunity']);
        $this->assertDatabaseHas('opportunities', ['name' => 'Test Opportunity']);
    }

    public function test_can_read_opportunity()
    {
        $opp = Opportunity::factory()->create();
        $found = Opportunity::find($opp->id);
        $this->assertNotNull($found);
        $this->assertEquals($opp->id, $found->id);
    }

    public function test_can_update_opportunity()
    {
        $opp = Opportunity::factory()->create();
        $opp->update(['name' => 'Updated Opportunity']);
        $this->assertDatabaseHas('opportunities', ['name' => 'Updated Opportunity']);
    }

    public function test_can_soft_delete_opportunity()
    {
        $opp = Opportunity::factory()->create();
        $opp->delete();
        $this->assertSoftDeleted($opp);
    }

    public function test_opportunity_can_be_linked_to_organization()
    {
        $opp = Opportunity::factory()->create();
        $org = Organization::factory()->create();
        $opp->organizations()->attach($org);
        $this->assertTrue($opp->organizations->contains($org));
    }

    public function test_opportunity_can_be_tagged()
    {
        $opp = Opportunity::factory()->create();
        $tag = Tag::factory()->create();
        $opp->tags()->attach($tag);
        $this->assertTrue($opp->tags->contains($tag));
    }

    public function test_opportunity_can_have_files()
    {
        $opp = Opportunity::factory()->create();
        $file = File::factory()->create();
        $opp->files()->attach($file);
        $this->assertTrue($opp->files->contains($file));
    }

    public function test_opportunity_can_sync_tags()
    {
        $opp = Opportunity::factory()->create();
        $tags = Tag::factory()->count(3)->create();
        $opp->tags()->sync($tags->pluck('id')->toArray());
        $this->assertCount(3, $opp->tags);
    }

    public function test_opportunity_can_detach_a_tag()
    {
        $opp = Opportunity::factory()->create();
        $tag = Tag::factory()->create();
        $opp->attachTag($tag);
        $opp->tags()->detach($tag);
        $opp->refresh();
        $this->assertFalse($opp->tags->contains($tag));
    }

    public function test_opportunity_files_are_deleted_when_opportunity_is_deleted()
    {
        $opp = Opportunity::factory()->create();
        $file = File::factory()->create();
        $opp->files()->attach($file);
        $opp->delete();
        $this->assertDatabaseMissing('fileables', [
            'fileable_id' => $opp->id,
            'fileable_type' => Opportunity::class,
            'file_id' => $file->id,
        ]);
    }

    public function test_created_by_is_set_on_create()
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $opp = Opportunity::factory()->create();

        $this->assertEquals($user->id, $opp->created_by);
    }

    public function test_updated_by_is_set_on_update()
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $opp = Opportunity::factory()->create();
        $opp->update(['name' => 'Changed Name']);

        $opp->refresh();
        $this->assertEquals($user->id, $opp->updated_by);
    }

    public function test_deleted_by_is_set_on_delete()
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $opp = Opportunity::factory()->create();
        $opp->delete();

        $opp = Opportunity::withTrashed()->find($opp->id);
        $this->assertEquals($user->id, $opp->deleted_by);
    }
}
