<?php

namespace Tests\Feature\Backend;

use App\Enums\PhanQuyenPermission;
use Tests\TestCase;

final class PhanQuyenScaffoldTest extends TestCase
{
    public function test_rbac_management_permissions_use_reserved_ids(): void
    {
        $this->assertSame([5, 6, 7, 8], array_map(fn ($permission) => $permission->id(), PhanQuyenPermission::cases()));
        $this->assertSame('PhanQuyen', PhanQuyenPermission::Sua->module());
    }
}
