<?php

namespace Tests\Feature\Configuration;

use Tests\TestCase;

class BusinessTimezoneTest extends TestCase
{
    public function test_business_timezone_defaults_are_consistent(): void
    {
        $this->assertSame('Asia/Ho_Chi_Minh', config('app.timezone'));
        $this->assertSame('+07:00', config('database.connections.mysql.timezone'));
        $this->assertSame('+07:00', config('database.connections.mariadb.timezone'));
    }
}
