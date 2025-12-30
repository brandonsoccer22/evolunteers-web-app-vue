<?php

use App\Models\Role;
use App\Models\User;

function createAdminUser(): User
{
    $adminRole = Role::firstOrCreate(['name' => Role::ADMIN]);
    $admin = User::factory()->create();
    $admin->roles()->syncWithoutDetaching([$adminRole->id]);

    return $admin;
}

function createOrganizationManager(): User
{
    $managerRole = Role::firstOrCreate(['name' => Role::ORGANIZATION_MANAGER]);
    $manager = User::factory()->create();
    $manager->roles()->syncWithoutDetaching([$managerRole->id]);

    return $manager;
}
