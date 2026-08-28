<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;

final class NhanVien extends Model implements AuthenticatableContract
{
    use Authenticatable;

    protected $table = 'nhan_vien';

    protected $primaryKey = 'ma_nv';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $hidden = ['mat_khau'];

    public function getAuthPasswordName(): string
    {
        return 'mat_khau';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void
    {
    }

    public function getRememberTokenName(): ?string
    {
        return null;
    }

    public static function fromAuthRow(object $row): self
    {
        $employee = (new self())->forceFill([
            'ma_nv' => $row->ma_nv,
            'ho_ten' => $row->ho_ten,
            'email' => $row->email,
            'mat_khau' => $row->mat_khau,
            'ma_vt' => $row->ma_vt,
            'ma_pb' => property_exists($row, 'ma_pb') ? $row->ma_pb : null,
            'ma_tt' => property_exists($row, 'ma_tt') ? $row->ma_tt : null,
        ]);
        $employee->exists = true;

        return $employee;
    }

}
