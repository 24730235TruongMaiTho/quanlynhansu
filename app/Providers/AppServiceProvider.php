<?php

namespace App\Providers;

use App\Auth\NhanVienUserProvider;
use App\Contracts\NhanVienRepositoryContract;
use App\Contracts\NhanVienServiceContract;
use App\Contracts\PhongBanRepositoryContract;
use App\Contracts\PhongBanServiceContract;
use App\Enums\NhanVienPermission;
use App\Enums\PhongBanPermission;
use App\Models\NhanVien;
use App\Repositories\NhanVienRepository;
use App\Repositories\PhongBanRepository;
use App\Services\NhanVienPermissionService;
use App\Services\NhanVienService;
use App\Services\PhongBanPermissionService;
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
        $this->app->bind(NhanVienServiceContract::class, NhanVienService::class);
        $this->app->bind(PhongBanRepositoryContract::class, PhongBanRepository::class);
        $this->app->bind(PhongBanServiceContract::class, PhongBanService::class);
        $this->app->scoped(NhanVienPermissionService::class);
        $this->app->scoped(PhongBanPermissionService::class);
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

        foreach (NhanVienPermission::cases() as $permission) {
            Gate::define($permission->value, function (mixed $actor) use ($permission): bool {
                return $actor instanceof NhanVien
                    && app(NhanVienPermissionService::class)->allows($actor, $permission);
            });
        }

        foreach (PhongBanPermission::cases() as $permission) {
            Gate::define($permission->value, function (mixed $actor) use ($permission): bool {
                return $actor instanceof NhanVien
                    && app(PhongBanPermissionService::class)->allows($actor, $permission);
            });
        }
    }
}
