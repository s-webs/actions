<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * По просьбе заказчика пароль мероприятия должен быть виден администратору в любой
 * момент (копирование), не только один раз при генерации — `password_hash` (bcrypt)
 * для этого не годится, он необратим. Добавлена `password` — то же значение, но
 * зашифрованное обратимо (`encrypted` cast на модели, `Crypt`/APP_KEY), а не открытым
 * текстом в БД. `password_hash` остаётся источником для самой аутентификации
 * (`Auth::attempt`) без изменений — `password` только для отображения.
 *
 * `login_token` — случайная строка для прямой ссылки входа (обходит форму
 * логин+пароль); хранится отдельно от пароля, чтобы ротация пароля не обязательно
 * рвала уже разосланную ссылку и наоборот (хотя `MeasureCredentialGenerator` сейчас
 * ротирует оба вместе — см. Result task в Obsidian).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('measure_credentials', function (Blueprint $table) {
            $table->text('password')->nullable()->after('password_hash');
            $table->string('login_token', 64)->nullable()->unique()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('measure_credentials', function (Blueprint $table) {
            $table->dropColumn(['password', 'login_token']);
        });
    }
};
