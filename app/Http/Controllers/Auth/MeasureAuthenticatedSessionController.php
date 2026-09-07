<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\MeasureLoginRequest;
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

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('measure')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect(route('measure.login'));
    }
}
