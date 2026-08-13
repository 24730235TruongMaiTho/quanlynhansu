<?php

namespace App\Providers;

use App\Contracts\NhanVienRepositoryContract;
use App\Contracts\NhanVienServiceContract;
use App\Repositories\NhanVienRepository;
use App\Services\NhanVienService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(NhanVienRepositoryContract::class, NhanVienRepository::class);
        $this->app->bind(NhanVienServiceContract::class, NhanVienService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
