<?php

namespace Database\Seeders;

use App\Helpers\DatabaseHelper;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\Role;
use Illuminate\Database\QueryException;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

       try {
             User::factory()->create([
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => 'test@example.com',
             ]);
        } catch (QueryException $e) {
            // Handle duplicate error
            if (DatabaseHelper::isDuplicateException($e)) { // 23000 is the SQLSTATE code for integrity constraint violation
                // ignore duplicate
            } else {
                throw $e; // rethrow if it's a different error
            }
        }

        $this->seedRoles();
        $this->assignAdminRoleToTestUser();
    }

    protected function seedRoles(): void
    {
        foreach ([Role::ADMIN, Role::ORGANIZATION_MANAGER, Role::USER] as $roleName) {
            Role::firstOrCreate(['name' => $roleName]);
        }
    }

    protected function assignAdminRoleToTestUser(): void
    {
        $user = User::where('email', 'test@example.com')->first();
        $adminRole = Role::where('name', Role::ADMIN)->first();

        if ($user && $adminRole) {
            $user->roles()->syncWithoutDetaching([$adminRole->id]);
        }
    }
}
