<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Opportunity;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_user()
    {
        $user = User::factory()->create(['first_name' => 'Test','last_name'=>'User']);
        $this->assertDatabaseHas('users', ['first_name' => 'Test','last_name'=>'User']);
        $this->assertDatabaseHas('users', ['name' => 'Test User']);
    }

    public function test_can_update_user()
    {
        $user = User::factory()->create();
        $user->update(['first_name' => 'Updated','last_name'=>'User']);
        $this->assertDatabaseHas('users', ['first_name' => 'Updated','last_name'=>'User']);
        $this->assertDatabaseHas('users', ['name' => 'Updated User']);
    }

    public function test_can_soft_delete_user()
    {
        $user = User::factory()->create();
        $user->delete();
        $this->assertSoftDeleted($user);
    }

    public function test_user_can_have_roles()
    {
        $user = User::factory()->create();
        $role = Role::factory()->create();
        $user->roles()->attach($role);
        $this->assertTrue($user->roles->contains($role));
    }

    public function test_user_can_join_opportunity()
    {
        $user = User::factory()->create();
        $opp = Opportunity::factory()->create();
        $user->opportunities()->attach($opp);
        $this->assertTrue($user->opportunities->contains($opp));
        $this->assertTrue($opp->users->contains($user));
    }

    public function test_created_by_is_set_on_create()
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $user = User::factory()->create();

        $this->assertEquals($admin->id, $user->created_by);
    }

    public function test_updated_by_is_set_on_update()
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $user = User::factory()->create();
        $user->update(['first_name' => 'Changed']);

        $user->refresh();
        $this->assertEquals($admin->id, $user->updated_by);
    }

    public function test_deleted_by_is_set_on_delete()
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $user = User::factory()->create();
        $user->delete();

        $user = User::withTrashed()->find($user->id);
        $this->assertEquals($admin->id, $user->deleted_by);
    }
}
