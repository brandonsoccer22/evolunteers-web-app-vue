<?php

use App\Models\File;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('can create organization', function (): void {
    Organization::factory()->create(['name' => 'Test Org']);

    $this->assertDatabaseHas('organizations', ['name' => 'Test Org']);
});

test('can read organization', function (): void {
    $org = Organization::factory()->create();
    $found = Organization::find($org->id);

    $this->assertNotNull($found);
    $this->assertEquals($org->id, $found->id);
});

test('can update organization', function (): void {
    $org = Organization::factory()->create();
    $org->update(['name' => 'Updated Org']);

    $this->assertDatabaseHas('organizations', ['name' => 'Updated Org']);
});

test('can soft delete organization', function (): void {
    $org = Organization::factory()->create();
    $org->delete();

    $this->assertSoftDeleted($org);
});

test('organization can create opportunity', function (): void {
    $org = Organization::factory()->create();
    $opp = Opportunity::factory()->create();
    $org->opportunities()->attach($opp);

    $this->assertTrue($org->opportunities->contains($opp));
});

test('organization can be tagged', function (): void {
    $org = Organization::factory()->create();
    $tag = Tag::factory()->create();
    $org->tags()->attach($tag);

    $this->assertTrue($org->tags->contains($tag));
});

test('organization can have files', function (): void {
    $org = Organization::factory()->create();
    $file = File::factory()->create();
    $org->files()->attach($file);

    $this->assertTrue($org->files->contains($file));
});

test('organization can sync tags', function (): void {
    $org = Organization::factory()->create();
    $tags = Tag::factory()->count(3)->create();
    $org->tags()->sync($tags->pluck('id')->toArray());

    $this->assertCount(3, $org->tags);
});

test('organization can detach a tag', function (): void {
    $org = Organization::factory()->create();
    $tag = Tag::factory()->create();
    $org->attachTag($tag);
    $org->tags()->detach($tag);
    $org->refresh();

    $this->assertFalse($org->tags->contains($tag));
});

test('organization files are deleted when organization is deleted', function (): void {
    $org = Organization::factory()->create();
    $file = File::factory()->create();
    $org->files()->attach($file);
    $org->delete();

    $this->assertDatabaseMissing('fileables', [
        'fileable_id' => $org->id,
        'fileable_type' => Organization::class,
        'file_id' => $file->id,
    ]);
});

test('created by is set on create', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $org = Organization::factory()->create();

    $this->assertEquals($user->id, $org->created_by);
});

test('updated by is set on update', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $org = Organization::factory()->create();
    $org->update(['name' => 'Changed Name']);

    $org->refresh();
    $this->assertEquals($user->id, $org->updated_by);
});

test('deleted by is set on delete', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $org = Organization::factory()->create();
    $org->delete();

    $org = Organization::withTrashed()->find($org->id);
    $this->assertEquals($user->id, $org->deleted_by);
});
