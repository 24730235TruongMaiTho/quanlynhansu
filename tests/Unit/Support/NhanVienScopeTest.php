<?php

namespace Tests\Unit\Support;

use App\Models\NhanVien;
use App\Support\NhanVienScope;
use Tests\TestCase;

class NhanVienScopeTest extends TestCase
{
    public function test_unscoped_actor_keeps_requested_filters(): void
    {
        $scope = new NhanVienScope;
        $filters = ['ma_pb' => 1, 'ma_cv' => null];

        $this->assertSame($filters, $scope->filtersFor($this->actor(['ma_vt' => 1]), $filters));
    }

    public function test_department_manager_cannot_override_their_department_filter(): void
    {
        $scope = new NhanVienScope;

        $this->assertSame(
            ['ma_pb' => 2, 'ma_cv' => null],
            $scope->filtersFor($this->actor(['ma_vt' => 4, 'ma_pb' => 2]), ['ma_pb' => 1, 'ma_cv' => null]),
        );
    }

    public function test_manager_without_a_valid_department_fails_closed(): void
    {
        $scope = new NhanVienScope;

        $this->assertNull($scope->filtersFor($this->actor(['ma_vt' => 4]), ['ma_pb' => 1]));
        $this->assertFalse($scope->canAccess(
            $this->actor(['ma_vt' => 4]),
            (object) ['ma_pb' => 1],
        ));
    }

    public function test_department_manager_can_access_only_their_department(): void
    {
        $scope = new NhanVienScope;
        $manager = $this->actor(['ma_vt' => 4, 'ma_pb' => 2]);

        $this->assertTrue($scope->canAccess($manager, (object) ['ma_pb' => 2]));
        $this->assertFalse($scope->canAccess($manager, (object) ['ma_pb' => 1]));
    }

    private function actor(array $overrides = []): NhanVien
    {
        return NhanVien::fromAuthRow((object) array_replace([
            'ma_nv' => '00001',
            'ho_ten' => 'Nguyễn An',
            'email' => 'an@example.test',
            'mat_khau' => 'hash',
            'ma_vt' => 1,
            'ma_pb' => null,
            'ma_tt' => 1,
        ], $overrides));
    }
}
