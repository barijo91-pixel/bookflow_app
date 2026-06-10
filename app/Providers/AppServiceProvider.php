<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
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
        // 페이지네이션을 Bootstrap 5 스타일로 (기본은 Tailwind SVG라 거대한 아이콘이 뜸)
        Paginator::useBootstrapFive();
    }
}
