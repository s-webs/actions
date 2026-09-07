<?php

namespace App\Http\Requests\Auth;

use App\Models\MeasureSession;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Вход по логину/паролю мероприятия (guard `measure`) —
 * [[Роли и права#Доступ к мероприятию (неименной)]]. Доступ не именной: любой, кто знает
 * логин/пароль, входит и работает по нему — подотчётность закрывается полем «ФИО и
 * должность» при подаче этапа (task-007), а не этой формой входа.
 */
class MeasureLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::guard('measure')->attempt($this->only('login', 'password'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'login' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        $credential = Auth::guard('measure')->user();

        MeasureSession::create([
            'measure_id' => $credential->measure_id,
            'session_id' => $this->session()->getId(),
            'ip' => $this->ip(),
            'user_agent' => $this->userAgent(),
            'started_at' => now(),
            'last_seen_at' => now(),
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'login' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('login')).'|'.$this->ip());
    }
}
