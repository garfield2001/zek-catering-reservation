<?php

use App\Http\Middleware\EnsureActiveSession;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequestContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [RequestContext::class, EnsureActiveSession::class, HandleInertiaRequests::class]);
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/admin/home');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (HttpException $exception, Request $request) {
            if ($exception->getStatusCode() === 403) {
                Log::warning('Authorization denied', ['user_id' => $request->user()?->id, 'request_id' => $request->attributes->get('request_id'), 'path' => $request->path()]);
            }
        });
    })->create();
