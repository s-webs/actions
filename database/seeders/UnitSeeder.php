<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

/**
 * ЗАГЛУШКА. Полный справочник подразделений академического блока ещё не получен от
 * заказчика — [[Справочники#Ещё нужно от заказчика]]. Минимальный набор для локальной
 * разработки/тестов; заменить реальными данными до продакшен-импорта (task-005/task-012).
 */
class UnitSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['УМЦ', 'Деканат факультета'] as $name) {
            Unit::firstOrCreate(['name' => $name]);
        }
    }
}
