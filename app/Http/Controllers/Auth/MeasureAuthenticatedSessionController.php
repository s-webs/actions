<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\MeasureLoginRequest;
use App\Models\MeasureCredential;
use App\Models\MeasureSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Вход/выход по guard `measure` — рабочее место одного мероприятия
 * ([[Функциональные требования#4.7 Рабочее место мероприятия]], реализуется в task-007).
 */
class MeasureAuthenticatedSessionController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('auth/measure-login', [
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(MeasureLoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('measure.workspace', absolute: false));
    }

    /**
     * Прямая ссылка входа — заменяет ручной ввод логина/пароля (решение заказчика,
     * `credentials/index.tsx`). Токен непубличен и высокоэнтропиен (40 случайных
     * символов, [[MeasureCredentialGenerator]]), поэтому отдельного подтверждения не
     * требует — как и обычный вход, открывает рабочее место **одного** мероприятия.
     * Если в браузере уже открыта сессия другого мероприятия — она сначала закрывается,
     * чтобы ссылка всегда вела в своё мероприятие, а не молча наследовала чужую сессию.
     */
    public function loginViaLink(Request $request, string $token): RedirectResponse
    {
        $credential = MeasureCredential::where('login_token', $token)->firstOrFail();

        if (Auth::guard('measure')->check()) {
            Auth::guard('measure')->logout();
        }

        $request->session()->regenerate();
        Auth::guard('measure')->login($credential);

        MeasureSession::create([
            'measure_id' => $credential->measure_id,
            'session_id' => $request->session()->getId(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'started_at' => now(),
            'last_seen_at' => now(),
        ]);

        return redirect()->route('measure.workspace');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('measure')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect(route('measure.login'));
    }
}
