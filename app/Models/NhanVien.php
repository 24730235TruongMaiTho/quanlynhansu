<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NhanVien extends Model
{
    use HasFactory;

    /**
     * Tên bảng trong database.
     */
    protected $table = 'nhan_vien';

    /**
     * Khóa chính của bảng.
     */
    protected $primaryKey = 'ma_nv';

    /**
     * ma_nv là VARCHAR, không phải INT.
     */
    protected $keyType = 'string';

    /**
     * ma_nv không tự động tăng.
     */
    public $incrementing = false;

    /**
     * Bảng không có created_at và updated_at.
     */
    public $timestamps = false;

    /**
     * Các cột được phép gán dữ liệu hàng loạt.
     */
    protected $fillable = [
        'ma_nv',
        'ho_ten',
        'ngay_sinh',
        'gioi_tinh',
        'sdt',
        'email',
        'ngay_vao_lam',
        'ma_pb',
        'ma_cv',
        'dan_toc',
        'cccd',
        'noi_cap_cccd',
        'hoc_van',
        'ma_tt',
        'mat_khau',
        'ma_vt',
    ];

    /**
     * Không trả mật khẩu khi chuyển model thành JSON hoặc array.
     */
    protected $hidden = [
        'mat_khau',
    ];

    /**
     * Ép kiểu dữ liệu.
     */
    protected function casts(): array
    {
        return [
            'ngay_sinh' => 'date:Y-m-d',
            'ngay_vao_lam' => 'date:Y-m-d',
            'gioi_tinh' => 'boolean',
            'ma_pb' => 'integer',
            'ma_cv' => 'integer',
            'ma_tt' => 'integer',
            'ma_vt' => 'integer',
        ];
    }

    /**
     * Nhân viên thuộc một phòng ban.
     */
    public function phongBan(): BelongsTo
    {
        return $this->belongsTo(
            PhongBan::class,
            'ma_pb',
            'ma_pb'
        );
    }

    /**
     * Nhân viên có một chức vụ.
     */
    public function chucVu(): BelongsTo
    {
        return $this->belongsTo(
            ChucVu::class,
            'ma_cv',
            'ma_cv'
        );
    }

    /**
     * Trạng thái làm việc của nhân viên.
     */
    public function trangThaiLamViec(): BelongsTo
    {
        return $this->belongsTo(
            TrangThaiLamViec::class,
            'ma_tt',
            'ma_tt'
        );
    }

    /**
     * Vai trò của nhân viên.
     */
    public function vaiTro(): BelongsTo
    {
        return $this->belongsTo(
            VaiTro::class,
            'ma_vt',
            'ma_vt'
        );
    }

    /**
     * Danh sách hợp đồng của nhân viên.
     */
    public function hopDongs(): HasMany
    {
        return $this->hasMany(
            HopDong::class,
            'ma_nv',
            'ma_nv'
        );
    }

    /**
     * Danh sách chấm công.
     */
    public function chamCongs(): HasMany
    {
        return $this->hasMany(
            ChamCong::class,
            'ma_nv',
            'ma_nv'
        );
    }

    /**
     * Danh sách đơn nghỉ phép.
     */
    public function nghiPheps(): HasMany
    {
        return $this->hasMany(
            NghiPhep::class,
            'ma_nv',
            'ma_nv'
        );
    }

    /**
     * Danh sách bảng lương.
     */
    public function luongs(): HasMany
    {
        return $this->hasMany(
            Luong::class,
            'ma_nv',
            'ma_nv'
        );
    }

    /**
     * Lịch sử hệ số lương.
     */
    public function lichSuHeSoLuongs(): HasMany
    {
        return $this->hasMany(
            LichSuHeSoLuong::class,
            'ma_nv',
            'ma_nv'
        );
    }
}
