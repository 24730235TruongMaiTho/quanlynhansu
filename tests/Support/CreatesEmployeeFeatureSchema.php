<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait CreatesEmployeeFeatureSchema
{
    protected function createEmployeeFeatureSchema(): void
    {
        Schema::create('phong_ban', function (Blueprint $table): void {
            $table->increments('ma_pb');
            $table->string('ten_pb', 100);
        });

        Schema::create('chuc_vu', function (Blueprint $table): void {
            $table->increments('ma_cv');
            $table->string('ten_cv', 100);
        });

        Schema::create('trang_thai_lam_viec', function (Blueprint $table): void {
            $table->increments('ma_tt');
            $table->string('ky_hieu', 20)->unique();
            $table->string('ten_tt', 50);
        });

        Schema::create('nhan_vien', function (Blueprint $table): void {
            $table->string('ma_nv', 5)->primary();
            $table->string('email', 100)->unique();
            $table->string('cccd', 12)->unique();
        });

        DB::table('phong_ban')->insert([
            ['ma_pb' => 1, 'ten_pb' => 'Kỹ thuật'],
        ]);
        DB::table('chuc_vu')->insert([
            ['ma_cv' => 1, 'ten_cv' => 'Lập trình viên'],
        ]);
        DB::table('trang_thai_lam_viec')->insert([
            ['ma_tt' => 1, 'ky_hieu' => 'DANG_LAM', 'ten_tt' => 'Đang làm việc'],
            ['ma_tt' => 2, 'ky_hieu' => 'THU_VIEC', 'ten_tt' => 'Thử việc'],
            ['ma_tt' => 3, 'ky_hieu' => 'DA_NGHI', 'ten_tt' => 'Đã nghỉ'],
            ['ma_tt' => 4, 'ky_hieu' => 'OTHER', 'ten_tt' => 'Trạng thái khác'],
        ]);
    }

    protected function dropEmployeeFeatureSchema(): void
    {
        Schema::dropIfExists('nhan_vien');
        Schema::dropIfExists('trang_thai_lam_viec');
        Schema::dropIfExists('chuc_vu');
        Schema::dropIfExists('phong_ban');
    }

    protected function insertEmployeeIdentity(
        string $maNv = 'NV001',
        string $email = 'existing@example.test',
        string $cccd = '001200000099',
    ): void {
        DB::table('nhan_vien')->insert([
            'ma_nv' => $maNv,
            'email' => $email,
            'cccd' => $cccd,
        ]);
    }
}
