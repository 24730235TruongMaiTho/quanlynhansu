<?php

namespace App\Enums;

enum NhanVienPermission: string
{
    case Xem = 'NHAN_VIEN_XEM';
    case Tao = 'NHAN_VIEN_TAO';
    case Sua = 'NHAN_VIEN_SUA';
    case Xoa = 'NHAN_VIEN_XOA';
    case DatLaiMatKhau = 'NHAN_VIEN_DAT_LAI_MAT_KHAU';
}
