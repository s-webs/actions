<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Три именные роли guard'а `web` — [[Роли и права#Администраторы]]. `proctor` и
 * `coordinator` слиты в единую `administrator` (роль-функция «проректор или другой
 * сотрудник с теми же полномочиями» — по решению заказчика координатор больше не
 * отдельная веб-учётка: заведение этапов теперь делает исполнитель по логину/паролю
 * мероприятия). `developer` — техническая роль с полным доступом, минуя все Policy
 * (см. `Gate::before` в `AppServiceProvider`), плюс просмотр журнала аудита всех
 * пользователей (тот же `/audit`, доступный и `administrator`).
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['developer', 'administrator', 'observer'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }
}
