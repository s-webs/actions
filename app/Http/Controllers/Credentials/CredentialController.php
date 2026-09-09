<?php

namespace App\Http\Controllers\Credentials;

use App\Http\Controllers\Controller;
use App\Models\Measure;
use App\Services\MeasureCredentialGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Учётные данные мероприятий — доступен проректору и координатору,
 * [[Функциональные требования#4.14 Модуль «Учётные данные мероприятий»]]. Логин, пароль
 * (в читаемом виде — [[Роли и права#Реализация]]) и прямая ссылка входа видны в таблице
 * постоянно, не только сразу после генерации/ротации (решение заказчика).
 */
class CredentialController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('manage', Measure::class);

        $measures = Measure::with(['credential', 'sessions'])->orderBy('number')->get();

        return Inertia::render('credentials/index', [
            'rows' => $measures->map(fn (Measure $m) => [
                'id' => $m->id,
                'number' => $m->number,
                'title' => $m->title,
                'login' => $m->credential?->login,
                'password' => $m->credential?->password,
                'login_url' => $m->credential?->login_token
                    ? route('measure.link-login', $m->credential->login_token)
                    : null,
                'rotated_at' => $m->credential?->rotated_at?->format('Y-m-d H:i'),
                'expires_at' => $m->credential?->expires_at?->format('Y-m-d'),
                'active_sessions' => $m->sessions->count(),
                'sessions' => $m->sessions->sortByDesc('started_at')->values()->map(fn ($s) => [
                    'id' => $s->id,
                    'ip' => $s->ip,
                    'user_agent' => $s->user_agent,
                    'started_at' => $s->started_at->format('Y-m-d H:i'),
                    'last_seen_at' => $s->last_seen_at?->format('Y-m-d H:i'),
                ]),
            ]),
        ]);
    }

    public function rotate(Request $request, Measure $measure): RedirectResponse
    {
        Gate::authorize('manage', Measure::class);

        app(MeasureCredentialGenerator::class)->generate($measure, $request->user());

        return back()->with('status', "Учётные данные №{$measure->number} перегенерированы — старая ссылка входа тоже отозвана.");
    }

    public function rotateAll(Request $request): RedirectResponse
    {
        Gate::authorize('manage', Measure::class);

        $generator = app(MeasureCredentialGenerator::class);

        $count = Measure::orderBy('number')->get()
            ->each(fn (Measure $measure) => $generator->generate($measure, $request->user()))
            ->count();

        return back()->with('status', "Перегенерировано {$count} комплектов учётных данных.");
    }

    public function terminateSessions(Request $request, Measure $measure): RedirectResponse
    {
        Gate::authorize('manage', Measure::class);

        app(MeasureCredentialGenerator::class)->terminateSessions($measure);

        return back()->with('status', 'Сессии завершены.');
    }

    public function setExpiry(Request $request, Measure $measure): RedirectResponse
    {
        Gate::authorize('manage', Measure::class);

        $data = $request->validate(['expires_at' => ['nullable', 'date']]);

        $measure->credential?->update(['expires_at' => $data['expires_at'] ?? null]);

        return back()->with('status', 'Срок действия обновлён.');
    }
}
