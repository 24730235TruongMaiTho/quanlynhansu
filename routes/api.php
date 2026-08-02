<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Backend\ChamCongController;
use App\Http\Controllers\Backend\ChucVuController;
use App\Http\Controllers\Backend\LuongController;
use App\Http\Controllers\Backend\NghiPhepController;

Route::middleware('api')->prefix('api')->group(function () {
    Route::apiResource('cham-cong', ChamCongController::class);
    Route::apiResource('chuc-vu', ChucVuController::class);
    Route::apiResource('luong', LuongController::class);
    Route::apiResource('nghi-phep', NghiPhepController::class);
});

