<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Роли `proctor` и `coordinator` больше не различаются как отдельные веб-учётки —
 * координатор перестал быть отдельным логином (заведение этапов перешло на логин
 * мероприятия), поэтому обе роли сливаются в `administrator`. Любой пользователь,
 * у которого была роль `coordinator`, получает `administrator` (если ещё не было) —
 * не теряет доступ. Данных не удаляем безвозвратно нельзя (кто раньше был proctor,
 * а кто coordinator — после слияния не восстановить), поэтому `down()` не разводит
 * обратно.
 */
return new class extends Migration
{
    public function up(): void
    {
        $proctorId = DB::table('roles')->where('name', 'proctor')->where('guard_name', 'web')->value('id');
        $coordinatorId = DB::table('roles')->where('name', 'coordinator')->where('guard_name', 'web')->value('id');

        if ($proctorId) {
            DB::table('roles')->where('id', $proctorId)->update(['name' => 'administrator']);
        } else {
            $proctorId = DB::table('roles')->insertGetId([
                'name' => 'administrator', 'guard_name' => 'web',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        if ($coordinatorId) {
            $coordinatorUserIds = DB::table('model_has_roles')->where('role_id', $coordinatorId)->pluck('model_id');

            foreach ($coordinatorUserIds as $userId) {
                DB::table('model_has_roles')->updateOrInsert(
                    ['role_id' => $proctorId, 'model_id' => $userId, 'model_type' => User::class],
                    ['role_id' => $proctorId, 'model_id' => $userId, 'model_type' => User::class],
                );
            }

            DB::table('model_has_roles')->where('role_id', $coordinatorId)->delete();
            DB::table('roles')->where('id', $coordinatorId)->delete();
        }

        DB::table('roles')->insertOrIgnore([
            ['name' => 'developer', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        //
    }
};
