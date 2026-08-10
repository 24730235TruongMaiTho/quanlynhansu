<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoaiPhep extends Model
{
    protected $table = 'loai_phep';
    protected $primaryKey = 'ma_lp';
    public $timestamps = false;

    protected $fillable = [
        'ten_lp',
    ];

    /**
     * Relation: LoaiPhep has many NghiPhep
     */
    public function nghiPhep()
    {
        return $this->hasMany(NghiPhep::class, 'ma_lp', 'ma_lp');
    }
}
