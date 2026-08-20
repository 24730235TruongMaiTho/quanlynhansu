<?php

namespace Tests\Unit\Support;

use App\Support\NhanVienTargetGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\TestCase;

class NhanVienTargetGuardTest extends TestCase
{
    public function test_exact_default_role_is_manageable(): void
    {
        $guard = new NhanVienTargetGuard;

        $guard->assertManageable((object) [
            'ma_nv' => 'NV001',
            'email' => 'employee@example.test',
            'ky_hieu_vai_tro' => 'NHAN_VIEN_MAC_DINH',
        ]);

        $this->addToAssertionCount(1);
    }

    public function test_every_non_exact_or_missing_role_fails_with_a_generic_authorization_exception(): void
    {
        foreach ([null, '', 'nhan_vien_mac_dinh', ' NHAN_VIEN_MAC_DINH ', 'QUAN_TRI'] as $role) {
            $employee = (object) [
                'ma_nv' => 'NV999',
                'email' => 'privileged@example.test',
            ];
            if ($role !== null) {
                $employee->ky_hieu_vai_tro = $role;
            }

            try {
                (new NhanVienTargetGuard)->assertManageable($employee);
                $this->fail('A non-exact role must fail closed.');
            } catch (AuthorizationException $exception) {
                $this->assertSame('This action is unauthorized.', $exception->getMessage());
                $this->assertStringNotContainsString('NV999', $exception->getMessage());
                $this->assertStringNotContainsString('privileged@example.test', $exception->getMessage());
                if (is_string($role) && $role !== '') {
                    $this->assertStringNotContainsString($role, $exception->getMessage());
                }
            }
        }
    }
}
