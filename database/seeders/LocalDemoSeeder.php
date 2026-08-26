<?php

namespace Database\Seeders;

use App\Enums\ChucVuPermission;
use App\Enums\HopDongPermission;
use App\Enums\NhanVienPermission;
use App\Enums\PhanQuyenPermission;
use App\Enums\PhongBanPermission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use LogicException;

/**
 * Dữ liệu tối thiểu để kiểm thử đăng nhập và phân quyền trên máy local.
 *
 * Chạy trực tiếp bằng:
 * php artisan db:seed --class=Database\\Seeders\\LocalDemoSeeder
 */
final class LocalDemoSeeder extends Seeder
{
    private const ADMIN_CODE = 'NV001';
    private const ADMIN_EMAIL = 'an.nguyen@company.com';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('LocalDemoSeeder chỉ được chạy trong môi trường local hoặc testing.');
        }

        $requiredTables = [
            'phong_ban', 'chuc_vu', 'vai_tro', 'quyen', 'vai_tro_quyen',
            'trang_thai_lam_viec', 'nhan_vien',
        ];

        foreach ($requiredTables as $table) {
            if (! Schema::hasTable($table)) {
                throw new LogicException("Thiếu bảng {$table}; hãy import schema trước khi chạy seeder.");
            }
        }

        DB::transaction(function (): void {
            $departmentId = $this->firstOrCreateDepartment();
            $positionId = $this->firstOrCreatePosition();
            $this->seedContractTypes();

            DB::table('vai_tro')->updateOrInsert(
                ['ma_vt' => 1],
                ['ten_vt' => 'Quản trị hệ thống', 'mo_ta' => 'Tài khoản quản trị dùng để kiểm thử local'],
            );
            // CRUD Nhân viên luôn gán vai trò mặc định mã 5 cho hồ sơ mới.
            DB::table('vai_tro')->updateOrInsert(
                ['ma_vt' => 5],
                ['ten_vt' => 'Nhân viên', 'mo_ta' => 'Vai trò mặc định cho nhân viên mới'],
            );

            // Form tạo và nghiệp vụ kết thúc hồ sơ cần đủ bốn trạng thái chuẩn.
            foreach ([
                1 => 'Thử việc',
                2 => 'Đang làm việc',
                3 => 'Tạm nghỉ không lương',
                4 => 'Đã nghỉ việc',
            ] as $statusId => $statusName) {
                DB::table('trang_thai_lam_viec')->updateOrInsert(
                    ['ma_tt' => $statusId],
                    ['ten_tt' => $statusName],
                );
            }

            $permissionIds = $this->seedPermissions();
            foreach ($permissionIds as $permissionId) {
                DB::table('vai_tro_quyen')->updateOrInsert([
                    'ma_vt' => 1,
                    'ma_quyen' => $permissionId,
                ]);
            }

            $existing = DB::table('nhan_vien')->where('ma_nv', self::ADMIN_CODE)->first(['email']);
            if ($existing !== null && mb_strtolower((string) $existing->email) !== self::ADMIN_EMAIL) {
                throw new LogicException('NV001 đã thuộc dữ liệu khác; seeder từ chối ghi đè.');
            }

            $employee = [
                    'ho_ten' => 'Nguyễn Văn An',
                    'ngay_sinh' => '1985-03-15',
                    'gioi_tinh' => 1,
                    'sdt' => '0901234567',
                    'email' => self::ADMIN_EMAIL,
                    'ngay_vao_lam' => '2015-01-10',
                    'ma_pb' => $departmentId,
                    'ma_cv' => $positionId,
                    'dan_toc' => 'Kinh',
                    'cccd' => '001085000001',
                    'noi_cap_cccd' => 'Cục CSQLHC về trật tự xã hội',
                    'hoc_van' => 'Thạc sĩ',
                    'ma_tt' => 2,
                    'mat_khau' => Hash::make('nhom3@2026'),
                    'ma_vt' => 1,
            ];

            $optional = [
                'dia_chi_cu_the' => 'Số 1 đường Demo', 'phuong_xa' => 'Phường Demo',
                'quan_huyen' => 'Quận Demo', 'tinh_thanh' => 'TP. Hồ Chí Minh',
                'anh_dai_dien' => null, 'ngay_nghi_viec' => null,
            ];
            $columns = Schema::getColumnListing('nhan_vien');
            foreach ($optional as $column => $value) {
                if (in_array($column, $columns, true)) {
                    $employee[$column] = $value;
                }
            }

            DB::table('nhan_vien')->updateOrInsert(['ma_nv' => self::ADMIN_CODE], $employee);

            if (Schema::hasTable('bo_dem_ma_nhan_vien')) {
                $currentCounter = (int) (DB::table('bo_dem_ma_nhan_vien')
                    ->where('ten_bo_dem', 'NHAN_VIEN')->value('so_da_cap') ?? 0);
                DB::table('bo_dem_ma_nhan_vien')->updateOrInsert(
                    ['ten_bo_dem' => 'NHAN_VIEN'],
                    ['so_da_cap' => max(1, $currentCounter)],
                );
            }
        });

        $this->command?->info('Đã tạo tài khoản local NV001 / nhom3@2026.');
    }

    private function firstOrCreateDepartment(): int
    {
        $existing = DB::table('phong_ban')->where('ten_pb', 'Phòng Công nghệ thông tin')->value('ma_pb');

        return $existing !== null
            ? (int) $existing
            : (int) DB::table('phong_ban')->insertGetId(['ten_pb' => 'Phòng Công nghệ thông tin'], 'ma_pb');
    }

    private function firstOrCreatePosition(): int
    {
        $existing = DB::table('chuc_vu')->where('ten_cv', 'Quản trị hệ thống')->value('ma_cv');

        return $existing !== null
            ? (int) $existing
            : (int) DB::table('chuc_vu')->insertGetId([
                'ten_cv' => 'Quản trị hệ thống',
                'he_so_phu_cap' => '1.00',
            ], 'ma_cv');
    }

    private function seedContractTypes(): void
    {
        if (! Schema::hasTable('loai_hop_dong')) {
            return;
        }

        foreach (['Không xác định thời hạn', 'Xác định thời hạn', 'Thử việc'] as $name) {
            if (! DB::table('loai_hop_dong')->where('ten_lhd', $name)->exists()) {
                DB::table('loai_hop_dong')->insert(['ten_lhd' => $name]);
            }
        }
    }

    /** @return list<int> */
    private function seedPermissions(): array
    {
        $labels = [
            'NV_VIEW' => 'Xem nhân viên', 'NV_CREATE' => 'Tạo nhân viên',
            'NV_EDIT' => 'Sửa nhân viên', 'NV_DELETE' => 'Xóa nhân viên',
            'NV_RESET_PASSWORD' => 'Đặt lại mật khẩu nhân viên',
            'PB_VIEW' => 'Xem phòng ban', 'PB_CREATE' => 'Tạo phòng ban',
            'PB_EDIT' => 'Sửa phòng ban', 'PB_DELETE' => 'Xóa phòng ban',
            'CV_VIEW' => 'Xem chức vụ', 'CV_CREATE' => 'Tạo chức vụ',
            'CV_EDIT' => 'Sửa chức vụ', 'CV_DELETE' => 'Xóa chức vụ',
            'HD_VIEW' => 'Xem hợp đồng', 'HD_CREATE' => 'Tạo hợp đồng',
            'HD_EDIT' => 'Sửa hợp đồng', 'HD_DELETE' => 'Xóa hợp đồng',
            'PQ_ROLE_VIEW' => 'Xem vai trò và phân quyền',
            'PQ_ROLE_MANAGE' => 'Quản lý vai trò và phân quyền',
        ];

        $definitions = [
            ...NhanVienPermission::cases(), ...PhongBanPermission::cases(),
            ...ChucVuPermission::cases(), ...HopDongPermission::cases(),
            ...PhanQuyenPermission::cases(),
        ];

        foreach ($definitions as $permission) {
            DB::table('quyen')->updateOrInsert(
                ['ma_quyen' => $permission->id()],
                [
                    'ky_hieu_quyen' => $permission->symbol(),
                    'ten_quyen' => $labels[$permission->symbol()],
                    'module' => $permission->module(),
                ],
            );
        }

        return array_map(static fn ($permission): int => $permission->id(), $definitions);
    }
}
