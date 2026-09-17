<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChamCong extends Model
{
    protected $table = 'cham_cong';
    protected $primaryKey = 'ma_cc';
    public $timestamps = false;

    protected $fillable = [
        'ma_nv',
        'ngay_lam',
        'so_gio_lam',
        'vao_muon',
        've_som',
    ];

    protected $casts = [
        'ngay_lam' => 'date',
        'so_gio_lam' => 'integer',
        'vao_muon' => 'boolean',
        've_som' => 'boolean',
    ];

    public function nhanVien()
    {
        return $this->belongsTo(NhanVien::class, 'ma_nv', 'ma_nv');
    }
}
?>
