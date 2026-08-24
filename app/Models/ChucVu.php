<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ChucVu extends Model
{
    protected $table = 'chuc_vu';

    protected $primaryKey = 'ma_cv';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = ['ten_cv', 'he_so_phu_cap'];

    protected $casts = ['he_so_phu_cap' => 'decimal:2'];

    public function nhanViens(): HasMany
    {
        return $this->hasMany(NhanVien::class, 'ma_cv', 'ma_cv');
    }
}
