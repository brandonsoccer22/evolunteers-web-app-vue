<?php

use App\Models\Opportunity;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('can create user', function (): void {
    User::factory()->create(['first_name' => 'Test', 'last_name' => 'User']);

    $this->assertDatabaseHas('users', ['first_name' => 'Test', 'last_name' => 'User']);
    $this->assertDatabaseHas('users', ['name' => 'Test User']);
});

test('can update user', function (): void {
    $user = User::factory()->create();
    $user->update(['first_name' => 'Updated', 'last_name' => 'User']);

    $this->assertDatabaseHas('users', ['first_name' => 'Updated', 'last_name' => 'User']);
    $this->assertDatabaseHas('users', ['name' => 'Updated User']);
});

test('can soft delete user', function (): void {
    $user = User::factory()->create();
    $user->delete();

    $this->assertSoftDeleted($user);
});

test('user can have roles', function (): void {
    $user = User::factory()->create();
    $role = Role::factory()->create();
    $user->roles()->attach($role);

    $this->assertTrue($user->roles->contains($role));
});

test('user can join opportunity', function (): void {
    $user = User::factory()->create();
    $opp = Opportunity::factory()->create();
    $user->opportunities()->attach($opp);

    $this->assertTrue($user->opportunities->contains($opp));
    $this->assertTrue($opp->users->contains($user));
});

test('created by is set on create', function (): void {
    $admin = User::factory()->create();
    $this->actingAs($admin);

    $user = User::factory()->create();

    $this->assertEquals($admin->id, $user->created_by);
});

test('updated by is set on update', function (): void {
    $admin = User::factory()->create();
    $this->actingAs($admin);

    $user = User::factory()->create();
    $user->update(['first_name' => 'Changed']);

    $user->refresh();
    $this->assertEquals($admin->id, $user->updated_by);
});

test('deleted by is set on delete', function (): void {
    $admin = User::factory()->create();
    $this->actingAs($admin);

    $user = User::factory()->create();
    $user->delete();

    $user = User::withTrashed()->find($user->id);
    $this->assertEquals($admin->id, $user->deleted_by);
});
