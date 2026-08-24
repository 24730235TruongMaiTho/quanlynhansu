<?php

namespace App\Enums;

enum NhanVienStatus: int
{
    case Probation = 1;
    case Working = 2;
    case UnpaidLeave = 3;
    case Terminated = 4;
}
