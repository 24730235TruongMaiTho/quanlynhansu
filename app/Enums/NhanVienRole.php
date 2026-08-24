<?php

namespace App\Enums;

enum NhanVienRole: int
{
    case SuperAdmin = 1;
    case HumanResources = 2;
    case CblAdmin = 3;
    case DepartmentManager = 4;
    case Employee = 5;
}
