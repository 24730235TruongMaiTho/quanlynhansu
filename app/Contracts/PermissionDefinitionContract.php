<?php

namespace App\Contracts;

use App\Enums\PermissionAction;

interface PermissionDefinitionContract
{
    public function id(): int;

    public function symbol(): string;

    public function module(): string;

    public function action(): ?PermissionAction;
}
