<?php

namespace App\Providers;

use App\Auth\NhanVienUserProvider;
use App\Authorization\PermissionRegistry;
use App\Contracts\NhanVienRepositoryContract;
use App\Contracts\NhanVienServiceContract;
use App\Contracts\ChucVuRepositoryContract;
use App\Contracts\ChucVuServiceContract;
use App\Contracts\PermissionRepositoryContract;
use App\Contracts\PermissionRegistryContract;
use App\Contracts\PhongBanRepositoryContract;
use App\Contracts\PhongBanServiceContract;
use App\Models\NhanVien;
use App\Repositories\NhanVienRepository;
use App\Repositories\ChucVuRepository;
use App\Repositories\PermissionRepository;
use App\Repositories\PhongBanRepository;
use App\Services\NhanVienService;
use App\Services\ChucVuService;
use App\Services\PermissionService;
use App\Services\PhongBanService;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(NhanVienRepositoryContract::class, NhanVienRepository::class);
        $this->app->bind(PermissionRepositoryContract::class, PermissionRepository::class);
        $this->app->singleton(PermissionRegistry::class);
        $this->app->singleton(PermissionRegistryContract::class, static fn (Application $app): PermissionRegistry => $app->make(PermissionRegistry::class));
        $this->app->bind(NhanVienServiceContract::class, NhanVienService::class);
        $this->app->bind(ChucVuRepositoryContract::class, ChucVuRepository::class);
        $this->app->bind(ChucVuServiceContract::class, ChucVuService::class);
        $this->app->bind(PhongBanRepositoryContract::class, PhongBanRepository::class);
        $this->app->bind(PhongBanServiceContract::class, PhongBanService::class);
        $this->app->scoped(PermissionService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Auth::provider('nhan-vien', function (Application $app, array $config): NhanVienUserProvider {
            return new NhanVienUserProvider(
                $app->make(NhanVienRepositoryContract::class),
                $app->make(Hasher::class),
            );
        });

        foreach (app(PermissionRegistry::class)->all() as $permission) {
            Gate::define($permission->symbol(), function (mixed $actor) use ($permission): bool {
                return $actor instanceof NhanVien
                    && app(PermissionService::class)->allows($actor, $permission);
            });
        }
    }
}
