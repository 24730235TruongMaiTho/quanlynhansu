<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LichSuHeSoLuong extends Model
{
	protected $table = 'lich_su_he_so_luong';

	protected $primaryKey = 'ma_ls';

	public $timestamps = false;

	protected $fillable = [
		'ma_nv',
		'he_so_luong',
		'tu_ngay',
		'den_ngay',
	];

	protected $casts = [
		'he_so_luong' => 'decimal:2',
		'tu_ngay' => 'date',
		'den_ngay' => 'date',
	];
}