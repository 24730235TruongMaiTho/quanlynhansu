<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VaiTro extends Model
{
	protected $table = 'vai_tro';

	protected $primaryKey = 'ma_vt';

	public $timestamps = false;

	protected $fillable = [
		'ten_vt',
		'mo_ta',
	];

}
