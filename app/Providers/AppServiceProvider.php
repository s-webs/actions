<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /**
         * Роль `developer` — «абсолютно все привилегии» (решение заказчика): проходит
         * любую Policy-проверку без исключений, минуя `MeasurePolicy`/`PeriodPolicy`/
         * `StagePeriodUpdatePolicy`/`EvidencePolicy` по отдельности. Guard `measure`
         * (MeasureCredential) под этот bypass не подпадает — у него нет ролей вообще.
         */
        Gate::before(fn ($user, string $ability) => $user instanceof User && $user->hasRole('developer') ? true : null);
    }
}
