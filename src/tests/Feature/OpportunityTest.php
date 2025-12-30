<?php

use App\Models\File;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\Tag;
use App\Models\Taggable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('can create opportunity', function (): void {
    Opportunity::factory()->create(['name' => 'Test Opportunity']);

    $this->assertDatabaseHas('opportunities', ['name' => 'Test Opportunity']);
});

test('can read opportunity', function (): void {
    $opp = Opportunity::factory()->create();
    $found = Opportunity::find($opp->id);

    $this->assertNotNull($found);
    $this->assertEquals($opp->id, $found->id);
});

test('can update opportunity', function (): void {
    $opp = Opportunity::factory()->create();
    $opp->update(['name' => 'Updated Opportunity']);

    $this->assertDatabaseHas('opportunities', ['name' => 'Updated Opportunity']);
});

test('can soft delete opportunity', function (): void {
    $opp = Opportunity::factory()->create();
    $opp->delete();

    $this->assertSoftDeleted($opp);
});

test('opportunity can be linked to organization', function (): void {
    $opp = Opportunity::factory()->create();
    $org = Organization::factory()->create();
    $opp->organizations()->attach($org);

    $this->assertTrue($opp->organizations->contains($org));
});

test('opportunity can be tagged', function (): void {
    $opp = Opportunity::factory()->create();
    $tag = Tag::factory()->create();
    $opp->tags()->attach($tag);

    $this->assertTrue($opp->tags->contains($tag));
});

test('opportunity can have files', function (): void {
    $opp = Opportunity::factory()->create();
    $file = File::factory()->create();
    $opp->files()->attach($file);

    $this->assertTrue($opp->files->contains($file));
});

test('opportunity can sync tags', function (): void {
    $opp = Opportunity::factory()->create();
    $tags = Tag::factory()->count(3)->create();
    $opp->tags()->sync($tags->pluck('id')->toArray());

    $this->assertCount(3, $opp->tags);
});

test('opportunity can detach a tag', function (): void {
    $opp = Opportunity::factory()->create();
    $tag = Tag::factory()->create();
    $opp->attachTag($tag);
    $opp->tags()->detach($tag);
    $opp->refresh();

    $this->assertFalse($opp->tags->contains($tag));
});

test('opportunity can restore soft deleted tag pivot', function (): void {
    $opp = Opportunity::factory()->create();
    $tag = Tag::factory()->create();
    $now = now();

    DB::table('taggables')->insert([
        'tag_id' => $tag->id,
        'taggable_id' => $opp->id,
        'taggable_type' => Opportunity::class,
        'created_at' => $now,
        'updated_at' => $now,
        'deleted_at' => $now,
    ]);

    $opp->attachTag($tag);

    $this->assertTrue($opp->fresh()->tags->contains($tag));
    $this->assertSame(1, Taggable::withTrashed()
        ->where('tag_id', $tag->id)
        ->where('taggable_id', $opp->id)
        ->where('taggable_type', Opportunity::class)
        ->count());
    $this->assertTrue(DB::table('taggables')
        ->where('tag_id', $tag->id)
        ->where('taggable_id', $opp->id)
        ->where('taggable_type', Opportunity::class)
        ->whereNull('deleted_at')
        ->exists());
});

test('opportunity files are deleted when opportunity is deleted', function (): void {
    $opp = Opportunity::factory()->create();
    $file = File::factory()->create();
    $opp->files()->attach($file);
    $opp->delete();

    $this->assertDatabaseMissing('fileables', [
        'fileable_id' => $opp->id,
        'fileable_type' => Opportunity::class,
        'file_id' => $file->id,
    ]);
});

test('created by is set on create', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $opp = Opportunity::factory()->create();

    $this->assertEquals($user->id, $opp->created_by);
});

test('updated by is set on update', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $opp = Opportunity::factory()->create();
    $opp->update(['name' => 'Changed Name']);

    $opp->refresh();
    $this->assertEquals($user->id, $opp->updated_by);
});

test('deleted by is set on delete', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $opp = Opportunity::factory()->create();
    $opp->delete();

    $opp = Opportunity::withTrashed()->find($opp->id);
    $this->assertEquals($user->id, $opp->deleted_by);
});
