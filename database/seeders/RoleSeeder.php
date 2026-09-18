<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Именные роли guard'а `web`. `proctor` и `coordinator` слиты в единую
 * `administrator`. `developer` — техническая роль с полным доступом, минуя все
 * Policy (см. `Gate::before` в `AppServiceProvider`). `responsible` — именной
 * вход через `/login` со скоупом на свои мероприятия; исполнители по-прежнему
 * входят по логину мероприятия (guard `measure`).
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['developer', 'administrator', 'observer', 'responsible'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }
}
