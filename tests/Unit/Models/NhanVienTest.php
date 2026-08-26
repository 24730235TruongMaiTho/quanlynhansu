<?php

namespace Tests\Unit\Models;

use App\Enums\NhanVienRemovalAction;
use App\Models\NhanVien;
use Tests\TestCase;

class NhanVienTest extends TestCase
{
    public function test_employee_model_uses_explicit_table_and_key_columns(): void
    {
        $employee = (new NhanVien())->forceFill([
            'ma_nv' => 'NV001',
            'ho_ten' => 'Nguyễn An',
            'email' => 'an@example.test',
            'mat_khau' => 'hash',
            'ma_vt' => 1,
            'ma_tt' => 2,
        ]);
        $employee->exists = true;

        $this->assertNotContains(\Illuminate\Contracts\Auth\Authenticatable::class, class_implements(NhanVien::class));
        $this->assertSame('nhan_vien', $employee->getTable());
        $this->assertSame('ma_nv', $employee->getKeyName());
        $this->assertSame('NV001', $employee->getKey());
        $this->assertTrue($employee->exists);
        $this->assertSame('Nguyễn An', $employee->ho_ten);
        $this->assertSame('an@example.test', $employee->email);
        $this->assertSame(1, $employee->ma_vt);
        $this->assertSame(2, $employee->ma_tt);
        $this->assertContains('mat_khau', $employee->getHidden());
    }

    public function test_employee_model_does_not_expose_authentication_methods(): void
    {
        $employee = new NhanVien();

        $this->assertFalse(method_exists($employee, 'getAuthPassword'));
        $this->assertFalse(method_exists($employee, 'getRememberToken'));
    }

    public function test_removal_actions_match_the_repository_contract(): void
    {
        $this->assertSame('DELETED', NhanVienRemovalAction::Deleted->value);
        $this->assertSame('TERMINATED', NhanVienRemovalAction::Terminated->value);
    }
}
