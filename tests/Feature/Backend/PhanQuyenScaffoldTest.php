<?php

namespace Tests\Feature\Backend;

use App\Enums\PhanQuyenPermission;
use Tests\TestCase;

final class PhanQuyenScaffoldTest extends TestCase
{
    public function test_rbac_management_permissions_use_reserved_ids(): void
    {
        $this->assertSame(801, PhanQuyenPermission::Xem->id());
        $this->assertSame(802, PhanQuyenPermission::QuanLy->id());
        $this->assertSame('PhanQuyen', PhanQuyenPermission::QuanLy->module());
    }
}
