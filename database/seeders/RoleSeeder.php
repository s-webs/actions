<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Три именные роли guard'а `web` — [[Роли и права#Администраторы]].
 * Права `%`/утверждение этапов проверяются через Policy (StagePolicy), а не через
 * отдельные spatie-permission'ы — единственная привилегированная роль — proctor.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['proctor', 'coordinator', 'observer'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }
}
