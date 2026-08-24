<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quyen extends Model
{
	protected $table = 'quyen';

	protected $primaryKey = 'ma_quyen';

	protected $keyType = 'int';

	public $incrementing = false;

	public $timestamps = false;

	protected $fillable = [
		'ma_quyen',
		'ky_hieu_quyen',
		'ten_quyen',
		'module',
	];

	protected $casts = [
		'ma_quyen' => 'integer',
	];
}