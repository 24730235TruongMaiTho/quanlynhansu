<?php

namespace Tests\Unit\Models;

use App\Models\NhanVien;
use App\Models\PhongBan;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\TestCase;

class PhongBanTest extends TestCase
{
    public function test_department_maps_canonical_table_key_timestamps_and_relation(): void
    {
        $department = new PhongBan(['ten_pb' => '  Kỹ thuật  ']);

        $this->assertSame('phong_ban', $department->getTable());
        $this->assertSame('ma_pb', $department->getKeyName());
        $this->assertSame('int', $department->getKeyType());
        $this->assertTrue($department->getIncrementing());
        $this->assertFalse($department->usesTimestamps());
        $this->assertSame('  Kỹ thuật  ', $department->ten_pb);
        $this->assertInstanceOf(HasMany::class, $department->nhanViens());
        $this->assertSame(NhanVien::class, $department->nhanViens()->getRelated()::class);
        $this->assertSame('ma_pb', $department->nhanViens()->getForeignKeyName());
        $this->assertSame(['ten_pb'], $department->getFillable());
    }
}
