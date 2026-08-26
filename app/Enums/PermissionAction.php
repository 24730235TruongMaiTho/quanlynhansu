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
        if (preg_match('/\A[A-Za-z][A-Za-z0-9]*\.(Read|Insert|Update|Delete)\z/', $symbol, $matches) !== 1) {
            return null;
        }

        return match ($matches[1]) {
            'Read' => self::View,
            'Insert' => self::Create,
            'Update' => self::Edit,
            'Delete' => self::Delete,
        };
    }
}
