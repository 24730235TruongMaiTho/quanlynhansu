<?php

namespace App\Enums;

enum NhanVienStatus: int
{
    case Working = 1;
    case Probation = 2;
    case Intern = 3;
    case Terminated = 4;
    case Dismissed = 5;
    case Retired = 6;

    public function isTerminal(): bool
    {
        return in_array($this, [self::Terminated, self::Dismissed, self::Retired], true);
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Working, self::Probation, self::Intern], true);
    }

    public static function isTerminalValue(int $value): bool
    {
        return self::tryFrom($value)?->isTerminal() ?? false;
    }

    public static function isActiveValue(int $value): bool
    {
        return self::tryFrom($value)?->isActive() ?? false;
    }

    public static function canTransitionValue(int $current, int $requested): bool
    {
        $currentStatus = self::tryFrom($current);
        $requestedStatus = self::tryFrom($requested);

        if ($currentStatus === null || $requestedStatus === null) {
            return false;
        }

        if ($currentStatus->isTerminal()) {
            return $currentStatus === $requestedStatus;
        }

        return $requestedStatus->isActive();
    }

    /** Trả về danh sách mã trạng thái terminal. @return list<int> */
    public static function terminalValues(): array
    {
        return [self::Terminated->value, self::Dismissed->value, self::Retired->value];
    }
}
