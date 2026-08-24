<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PhongBan extends Model
{
    protected $table = 'phong_ban';

    protected $primaryKey = 'ma_pb';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = ['ten_pb'];

    public function nhanViens(): HasMany
    {
        return $this->hasMany(NhanVien::class, 'ma_pb', 'ma_pb');
    }
}
