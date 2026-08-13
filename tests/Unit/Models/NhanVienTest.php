<?php

namespace Tests\Unit\Models;

use App\Enums\NhanVienRemovalAction;
use App\Models\NhanVien;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Tests\TestCase;

class NhanVienTest extends TestCase
{
    public function test_employee_auth_mapping_uses_legacy_columns(): void
    {
        $employee = NhanVien::fromAuthProcedureRow((object) [
            'ma_nv' => 'NV001',
            'ho_ten' => 'Nguyễn An',
            'email' => 'an@example.test',
            'mat_khau' => 'hash',
            'ma_vt' => 1,
            'ky_hieu' => 'DANG_LAM',
        ]);

        $this->assertContains(AuthenticatableContract::class, class_implements(NhanVien::class));
        $this->assertSame('nhan_vien', $employee->getTable());
        $this->assertSame('ma_nv', $employee->getKeyName());
        $this->assertSame('NV001', $employee->getKey());
        $this->assertTrue($employee->exists);
        $this->assertSame('Nguyễn An', $employee->ho_ten);
        $this->assertSame('an@example.test', $employee->email);
        $this->assertSame(1, $employee->ma_vt);
        $this->assertSame('DANG_LAM', $employee->ky_hieu);
        $this->assertSame('mat_khau', $employee->getAuthPasswordName());
        $this->assertSame('hash', $employee->getAuthPassword());
        $this->assertContains('mat_khau', $employee->getHidden());
    }

    public function test_employee_does_not_support_remember_tokens(): void
    {
        $employee = new NhanVien();

        $employee->setRememberToken('must-not-be-persisted');

        $this->assertNull($employee->getRememberTokenName());
        $this->assertNull($employee->getRememberToken());
        $this->assertArrayNotHasKey('remember_token', $employee->getAttributes());
    }

    public function test_removal_actions_match_the_procedure_contract(): void
    {
        $this->assertSame('DELETED', NhanVienRemovalAction::Deleted->value);
        $this->assertSame('TERMINATED', NhanVienRemovalAction::Terminated->value);
    }
}
