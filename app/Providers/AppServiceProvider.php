<?php

namespace App\Providers;

use App\Contracts\NhanVienRepositoryContract;
use App\Contracts\NhanVienServiceContract;
use App\Contracts\ChucVuRepositoryContract;
use App\Contracts\ChucVuServiceContract;
use App\Contracts\PhongBanRepositoryContract;
use App\Contracts\PhongBanServiceContract;
use App\Repositories\NhanVienRepository;
use App\Repositories\ChucVuRepository;
use App\Repositories\PhongBanRepository;
use App\Services\NhanVienService;
use App\Services\ChucVuService;
use App\Services\PhongBanService;
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
        $this->app->bind(ChucVuRepositoryContract::class, ChucVuRepository::class);
        $this->app->bind(ChucVuServiceContract::class, ChucVuService::class);
        $this->app->bind(PhongBanRepositoryContract::class, PhongBanRepository::class);
        $this->app->bind(PhongBanServiceContract::class, PhongBanService::class);
    }
}
