<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin'                => \App\Http\Middleware\EnsureAdmin::class,
            'require.pw.change'    => \App\Http\Middleware\RequirePasswordChange::class,
            'admin.session.timeout'=> \App\Http\Middleware\AdminSessionTimeout::class,
        ]);

        // 모든 웹 요청에 보안 헤더 + 인증된 요청에 비밀번호 강제 변경 체크
        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\RequirePasswordChange::class,
        ]);
        $middleware->redirectGuestsTo(function ($request) {
            // API 또는 JSON 요청은 리다이렉트 대신 401 JSON 응답 트리거 (null 반환)
            if ($request->is('api/*') || $request->expectsJson()) {
                return null;
            }
            // 관리자 영역은 관리자 로그인, 그 외는 공개 로그인으로
            return $request->is('admin/*') ? route('admin.login') : route('public.login');
        });
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
