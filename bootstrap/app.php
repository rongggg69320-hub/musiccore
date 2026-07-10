<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi(); // For SPA/Web apps
        $middleware->validateCertificates(false); // Only if you have local SSL issues
<<<<<<< HEAD

        $middleware->alias([
            'check.status' => \App\Http\Middleware\CheckUserStatus::class,
        ]);

        $middleware->appendToGroup('api', [
            \App\Http\Middleware\CheckUserStatus::class,
        ]);
=======
>>>>>>> 8c54516e6132e3c91e402289761ec5a395adc70f
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
