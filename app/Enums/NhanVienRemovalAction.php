<?php

namespace App\Enums;

enum NhanVienRemovalAction: string
{
    case Deleted = 'DELETED';
    case Terminated = 'TERMINATED';
}
