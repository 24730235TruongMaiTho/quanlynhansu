<?php

namespace Tests\Feature\Backend;

use App\Enums\HopDongPermission;
use Tests\TestCase;

final class HopDongScaffoldTest extends TestCase
{
    public function test_contract_permissions_and_warning_configuration_are_canonical(): void
    {
        $this->assertSame([21, 22, 23, 24], array_map(fn ($permission) => $permission->id(), HopDongPermission::cases()));
        $this->assertSame(30, config('hopdong.expiring_warning_days'));
    }
}
