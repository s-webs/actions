<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Два guard'а (web — администраторы, measure — рабочее место мероприятия) ведут
        // на разные экраны входа/старта — различаем по префиксу пути, см.
        // [[Роли и права]].
        $middleware->redirectTo(
            guests: fn ($request) => $request->is('measure/*') ? route('measure.login') : route('login'),
            users: fn ($request) => $request->is('measure/*') ? route('measure.workspace') : route('dashboard'),
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
