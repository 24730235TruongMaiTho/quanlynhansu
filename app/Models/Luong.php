<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Luong extends Model
{
    protected $table = 'luong';
    protected $primaryKey = 'ma_luong';
    public $timestamps = false;

    protected $fillable = [
        'ma_nv',
        'ky_luong',
        'thuong',
        'phat',
        'bao_hiem',
        'thue',
    ];

    protected $casts = [
        'thuong' => 'decimal:2',
        'phat' => 'decimal:2',
        'bao_hiem' => 'decimal:2',
        'thue' => 'decimal:2',
    ];

    public function nhanVien()
    {
        return $this->belongsTo(NhanVien::class, 'ma_nv', 'ma_nv');
    }
}
