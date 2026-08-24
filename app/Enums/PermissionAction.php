<?php

namespace App\Enums;

enum PermissionAction: string
{
    case View = 'VIEW';
    case Create = 'CREATE';
    case Edit = 'EDIT';
    case Delete = 'DELETE';

    public static function fromSymbol(string $symbol): ?self
    {
        foreach (self::cases() as $action) {
            if (str_ends_with($symbol, '_'.$action->value)) {
                return $action;
            }
        }

        return null;
    }
}
