<?php

namespace Tests\Support;

trait InteractsWithEmployeeModule
{
    protected function enableEmployeeModule(): void
    {
        config()->set('nhanvien.enabled', true);
    }
}
