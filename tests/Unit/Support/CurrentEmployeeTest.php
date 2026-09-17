<?php

namespace Tests\Unit\Support;

use App\Models\NhanVien;
use App\Support\CurrentEmployee;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class CurrentEmployeeTest extends TestCase
{
    public function test_it_returns_only_a_canonical_employee_identifier(): void
    {
        $actor = NhanVien::fromAuthRow((object) [
            'ma_nv' => '00007',
            'ho_ten' => 'Nhân viên',
            'email' => 'employee@example.test',
            'mat_khau' => 'hash',
            'ma_vt' => 5,
            'ma_pb' => 1,
            'ma_tt' => 1,
        ]);

        self::assertSame('00007', (new CurrentEmployee())->id($actor));
    }

    #[DataProvider('invalidActors')]
    public function test_it_fails_closed_for_non_employee_or_malformed_identity(?object $actor): void
    {
        try {
            (new CurrentEmployee())->id($actor);
            self::fail('Invalid actor must fail closed.');
        } catch (HttpException $exception) {
            self::assertSame(403, $exception->getStatusCode());
            self::assertSame('Không xác định được nhân viên hiện tại.', $exception->getMessage());
        }
    }

    public static function invalidActors(): array
    {
        $bad = NhanVien::fromAuthRow((object) [
            'ma_nv' => 'ABC',
            'ho_ten' => 'Sai',
            'email' => 'bad@example.test',
            'mat_khau' => 'hash',
            'ma_vt' => 5,
            'ma_pb' => 1,
            'ma_tt' => 1,
        ]);

        return [
            'null actor' => [null],
            'malformed employee identifier' => [$bad],
        ];
    }
}
