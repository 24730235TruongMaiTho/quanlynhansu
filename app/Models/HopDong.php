<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HopDong extends Model
{
	protected $table = 'hop_dong';

	protected $primaryKey = 'ma_hd';

	public $timestamps = false;

	protected $fillable = [
		'ma_nv',
		'ma_lhd',
		'ngay_ky',
		'ngay_het_han',
		'luong_co_ban',
	];

	protected $casts = [
		'ma_lhd' => 'integer',
		'ngay_ky' => 'date',
		'ngay_het_han' => 'date',
		'luong_co_ban' => 'decimal:0',
	];
}