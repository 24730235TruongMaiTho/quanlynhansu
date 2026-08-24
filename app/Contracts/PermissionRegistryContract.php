<?php

namespace App\Contracts;

use App\Enums\PermissionAction;

interface PermissionRegistryContract
{
    public function forAbility(string $ability): ?PermissionDefinitionContract;

    public function forModuleAction(string $module, PermissionAction $action): ?PermissionDefinitionContract;

    /** @return list<PermissionDefinitionContract> */
    public function all(): array;
}
