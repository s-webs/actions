<?php

namespace Database\Seeders;

use App\Models\Responsible;
use App\Models\Unit;
use Illuminate\Database\Seeder;

/**
 * ЗАГЛУШКА. Справочник должностей-ответственных и их привязка к каждому из 51
 * мероприятия ещё не получены от заказчика — [[Справочники#Ещё нужно от заказчика]].
 * Минимальный набор для локальной разработки/тестов; заменить реальными данными до
 * продакшен-импорта (task-005).
 */
class ResponsibleSeeder extends Seeder
{
    public function run(): void
    {
        $umc = Unit::where('name', 'УМЦ')->first();

        Responsible::firstOrCreate(
            ['name' => 'Руководитель УМЦ'],
            ['unit_id' => $umc?->id],
        );
    }
}
