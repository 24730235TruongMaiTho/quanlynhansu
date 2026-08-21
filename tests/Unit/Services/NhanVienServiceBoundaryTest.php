<?php

namespace Tests\Unit\Services;

use App\Contracts\NhanVienRepositoryContract;
use App\Contracts\NhanVienServiceContract;
use App\Http\Controllers\Backend\NhanVienController;
use App\Repositories\NhanVienRepository;
use App\Services\NhanVienService;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\DatabaseManager;
use Illuminate\Filesystem\FilesystemManager;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

class NhanVienServiceBoundaryTest extends TestCase
{
    public function test_employee_create_boundary_has_only_employee_dependencies_and_scalar_result(): void
    {
        $controllerTypes = $this->constructorTypes(NhanVienController::class);
        $serviceTypes = $this->constructorTypes(NhanVienService::class);
        $repositoryTypes = $this->constructorTypes(NhanVienRepository::class);

        $this->assertSame([NhanVienServiceContract::class], $controllerTypes);
        $this->assertSame([
            DatabaseManager::class,
            NhanVienRepositoryContract::class,
            FilesystemManager::class,
            Hasher::class,
        ], $serviceTypes);
        $this->assertContains(DatabaseManager::class, $repositoryTypes);
        foreach ([...$controllerTypes, ...$serviceTypes, ...$repositoryTypes] as $type) {
            $this->assertStringNotContainsString('HopDong', $type);
            $this->assertStringNotContainsString('ContractPayload', $type);
        }

        $serviceCreate = new ReflectionMethod(NhanVienServiceContract::class, 'create');
        $this->assertSame(['array'], array_map(
            fn ($parameter): string => (string) $parameter->getType(),
            $serviceCreate->getParameters(),
        ));
        $this->assertSame('string', (string) $serviceCreate->getReturnType());

        $repositoryCreate = new ReflectionMethod(NhanVienRepositoryContract::class, 'create');
        $this->assertSame(['array', 'string', '?string'], array_map(
            fn ($parameter): string => (string) $parameter->getType(),
            $repositoryCreate->getParameters(),
        ));
        $this->assertSame('string', (string) $repositoryCreate->getReturnType());

        $serviceUpdate = new ReflectionMethod(NhanVienServiceContract::class, 'update');
        $this->assertSame(['string', 'array'], array_map(
            fn ($parameter): string => (string) $parameter->getType(),
            $serviceUpdate->getParameters(),
        ));
        $this->assertSame('object', (string) $serviceUpdate->getReturnType());

        $repositoryUpdate = new ReflectionMethod(NhanVienRepositoryContract::class, 'update');
        $this->assertSame(['string', 'array'], array_map(
            fn ($parameter): string => (string) $parameter->getType(),
            $repositoryUpdate->getParameters(),
        ));
        $this->assertSame('void', (string) $repositoryUpdate->getReturnType());

        $repositoryAvatar = new ReflectionMethod(NhanVienRepositoryContract::class, 'replaceAvatarPath');
        $this->assertSame(['string', '?string'], array_map(
            fn ($parameter): string => (string) $parameter->getType(),
            $repositoryAvatar->getParameters(),
        ));
        $this->assertSame('?string', (string) $repositoryAvatar->getReturnType());
    }

    public function test_container_resolves_the_employee_service_without_any_employment_contract_module(): void
    {
        $this->assertFalse(class_exists('App\\Contracts\\HopDongRepositoryContract'));
        $this->assertFalse(class_exists('App\\Contracts\\HopDongServiceContract'));
        $this->assertInstanceOf(NhanVienService::class, $this->app->make(NhanVienService::class));
    }

    private function constructorTypes(string $class): array
    {
        $constructor = (new ReflectionClass($class))->getConstructor();
        $this->assertNotNull($constructor);

        return array_map(
            fn ($parameter): string => (string) $parameter->getType(),
            $constructor->getParameters(),
        );
    }
}
