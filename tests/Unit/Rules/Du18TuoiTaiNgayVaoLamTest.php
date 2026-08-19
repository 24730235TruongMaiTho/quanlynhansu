<?php

namespace Tests\Unit\Rules;

use App\Rules\Du18TuoiTaiNgayVaoLam;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class Du18TuoiTaiNgayVaoLamTest extends TestCase
{
    public function test_accepts_employee_on_their_exact_eighteenth_birthday(): void
    {
        $this->assertTrue($this->passes('2008-08-12', '2026-08-12'));
    }

    public function test_rejects_employee_one_day_before_their_eighteenth_birthday(): void
    {
        $this->assertFalse($this->passes('2008-08-13', '2026-08-12'));
    }

    public function test_handles_a_leap_day_birthday_on_the_calendar_boundary(): void
    {
        $this->assertFalse($this->passes('2008-02-29', '2026-02-28'));
        $this->assertTrue($this->passes('2008-02-29', '2026-03-01'));
    }

    public function test_does_not_replace_date_rules_for_invalid_or_missing_paired_dates(): void
    {
        $this->assertTrue($this->passes('2024-02-31', '2026-08-12'));
        $this->assertTrue($this->passes('2000-08-12', 'not-a-date'));
        $this->assertTrue($this->passes('2000-08-12', null));
    }

    private function passes(mixed $ngaySinh, mixed $ngayVaoLam): bool
    {
        return Validator::make(
            ['ngay_sinh' => $ngaySinh, 'ngay_vao_lam' => $ngayVaoLam],
            ['ngay_sinh' => [new Du18TuoiTaiNgayVaoLam]],
        )->passes();
    }
}
