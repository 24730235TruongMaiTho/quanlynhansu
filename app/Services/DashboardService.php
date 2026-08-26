<?php

namespace App\Services;

use App\Enums\NhanVienStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service xử lý dữ liệu cho Dashboard tổng quan
 *
 * @package App\Services
 */
class DashboardService
{
    /**
     * Lấy tất cả dữ liệu cho Dashboard
     *
     * @return array
     */
    public function getOverview(): array
    {
        return [
            'nhan_vien_theo_hoc_van' => $this->getEmployeeCountByEducation(),
            'nhan_vien_theo_phong_ban' => $this->getEmployeeCountByDepartment(),
            'hop_dong_sap_het_han' => $this->getExpiringContracts(),
            'bao_cao_cham_cong' => $this->getAttendanceReport(),
            'bao_cao_luong' => $this->getSalaryReport(),
        ];
    }

    /**
     * Thống kê số lượng nhân viên theo học vấn (dùng cho biểu đồ tròn)
     *
     * @return array
     */
    public function getEmployeeCountByEducation(): array
    {
        try {
            $result = DB::table('nhan_vien')
                ->select('hoc_van', DB::raw('COUNT(*) as total'))
                ->whereNotNull('hoc_van')
                ->whereNotIn('ma_tt', NhanVienStatus::terminalValues())
                ->groupBy('hoc_van')
                ->orderBy('total', 'DESC')
                ->get()
                ->toArray();

            // Nếu không có dữ liệu, trả về mảng rỗng
            if (empty($result)) {
                return [];
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('[DashboardService] Lỗi lấy thống kê học vấn: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Thống kê số lượng nhân viên theo phòng ban (dùng cho biểu đồ cột)
     *
     * @return array
     */
    public function getEmployeeCountByDepartment(): array
    {
        try {
            $result = DB::table('nhan_vien as nv')
                ->join('phong_ban as pb', 'nv.ma_pb', '=', 'pb.ma_pb')
                ->select('pb.ma_pb', 'pb.ten_pb', DB::raw('COUNT(nv.ma_nv) as total'))
                ->whereNotIn('nv.ma_tt', NhanVienStatus::terminalValues())
                ->groupBy('pb.ma_pb', 'pb.ten_pb')
                ->orderBy('total', 'DESC')
                ->get()
                ->toArray();

            if (empty($result)) {
                return [];
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('[DashboardService] Lỗi lấy thống kê phòng ban: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Danh sách hợp đồng sắp hết hạn trong vòng X ngày tới
     *
     * @param int $days Số ngày cần kiểm tra (mặc định 30)
     * @return array
     */
    public function getExpiringContracts(int $days = 30): array
    {
        try {
            $days = max(0, min($days, 365));
            $today = now()->startOfDay();
            $todayDate = $today->toDateString();
            $expiryDate = $today->copy()->addDays($days)->toDateString();

            $result = DB::table('hop_dong as hd')
                ->join('nhan_vien as nv', 'hd.ma_nv', '=', 'nv.ma_nv')
                ->join('loai_hop_dong as lhd', 'hd.ma_lhd', '=', 'lhd.ma_lhd')
                ->select([
                    'nv.ho_ten',
                    'nv.ma_nv',
                    'lhd.ten_lhd as ten_loai_hop_dong',
                    'hd.ngay_ky as ngay_bat_dau',
                    'hd.ngay_het_han as ngay_ket_thuc',
                ])
                ->whereBetween('hd.ngay_het_han', [$todayDate, $expiryDate])
                ->whereNotIn('nv.ma_tt', NhanVienStatus::terminalValues())
                ->orderBy('hd.ngay_het_han', 'ASC')
                ->limit(10)
                ->get()
                ->map(function (object $contract) use ($today): object {
                    $contract->so_ngay_con_lai = $today->diffInDays(
                        Carbon::parse((string) $contract->ngay_ket_thuc)->startOfDay()
                    );

                    return $contract;
                })
                ->all();

            if (empty($result)) {
                return [];
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('[DashboardService] Lỗi lấy hợp đồng sắp hết hạn: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Báo cáo chấm công cho tháng hiện tại
     *
     * @return array
     */
    public function getAttendanceReport(): array
    {
        try {
            $month = (int) now()->month;
            $year = (int) now()->year;

            $data = DB::table('cham_cong as cc')
                ->join('nhan_vien as nv', 'cc.ma_nv', '=', 'nv.ma_nv')
                ->whereMonth('cc.ngay_lam', $month)
                ->whereYear('cc.ngay_lam', $year)
                ->whereNotIn('nv.ma_tt', NhanVienStatus::terminalValues())
                ->select([
                    DB::raw('COUNT(DISTINCT cc.ma_nv) as tong_nhan_vien'),
                    DB::raw('COUNT(cc.ma_cc) as tong_ca_cham_cong'),
                    DB::raw('SUM(CASE WHEN cc.vao_muon = 1 THEN 1 ELSE 0 END) as so_lan_vao_muon'),
                    DB::raw('SUM(CASE WHEN cc.ve_som = 1 THEN 1 ELSE 0 END) as so_lan_ve_som'),
                    DB::raw('ROUND(COALESCE(AVG(cc.so_gio_lam), 0), 2) as gio_lam_trung_binh')
                ])
                ->first();

            return [
                'thang' => $month,
                'nam' => $year,
                'tong_nhan_vien' => (int) ($data->tong_nhan_vien ?? 0),
                'tong_ca_cham_cong' => (int) ($data->tong_ca_cham_cong ?? 0),
                'so_lan_vao_muon' => (int) ($data->so_lan_vao_muon ?? 0),
                'so_lan_ve_som' => (int) ($data->so_lan_ve_som ?? 0),
                'gio_lam_trung_binh' => (float) ($data->gio_lam_trung_binh ?? 0),
            ];
        } catch (\Exception $e) {
            Log::error('[DashboardService] Lỗi lấy báo cáo chấm công: ' . $e->getMessage());
            return [
                'thang' => (int) now()->month,
                'nam' => (int) now()->year,
                'tong_nhan_vien' => 0,
                'tong_ca_cham_cong' => 0,
                'so_lan_vao_muon' => 0,
                'so_lan_ve_som' => 0,
                'gio_lam_trung_binh' => 0,
            ];
        }
    }

    /**
     * Tổng hợp các khoản điều chỉnh lương đã ghi nhận cho tháng hiện tại.
     * Bảng luong không có cột lương net; chỉ tổng hợp thưởng, phạt, bảo hiểm và thuế.
     *
     * @return array
     */
    public function getSalaryReport(): array
    {
        try {
            $month = (int) now()->month;
            $year = (int) now()->year;
            $data = DB::table('luong as l')
                ->join('nhan_vien as nv', 'l.ma_nv', '=', 'nv.ma_nv')
                ->whereYear('l.ky_luong', $year)
                ->whereMonth('l.ky_luong', $month)
                ->whereNotIn('nv.ma_tt', NhanVienStatus::terminalValues())
                ->select([
                    DB::raw('COUNT(DISTINCT l.ma_nv) as so_nguoi'),
                    DB::raw('COALESCE(SUM(COALESCE(l.thuong, 0) - COALESCE(l.phat, 0) - COALESCE(l.bao_hiem, 0) - COALESCE(l.thue, 0)), 0) as tong_dieu_chinh'),
                    DB::raw('COALESCE(AVG(COALESCE(l.thuong, 0) - COALESCE(l.phat, 0) - COALESCE(l.bao_hiem, 0) - COALESCE(l.thue, 0)), 0) as dieu_chinh_trung_binh')
                ])
                ->first();

            if (!$data || (int) $data->so_nguoi === 0) {
                return [
                    'thang' => $month,
                    'nam' => $year,
                    'so_nguoi' => 0,
                    'tong_dieu_chinh' => 0,
                    'dieu_chinh_trung_binh' => 0,
                    'error' => 'Chưa có dữ liệu khoản điều chỉnh lương cho tháng ' . $month . '/' . $year
                ];
            }

            return [
                'thang' => $month,
                'nam' => $year,
                'so_nguoi' => (int) $data->so_nguoi,
                'tong_dieu_chinh' => (float) $data->tong_dieu_chinh,
                'dieu_chinh_trung_binh' => (float) $data->dieu_chinh_trung_binh,
            ];

        } catch (\Exception $e) {
            Log::error('[DashboardService] Lỗi lấy báo cáo lương: ' . $e->getMessage());
            return [
                'thang' => (int) now()->month,
                'nam' => (int) now()->year,
                'so_nguoi' => 0,
                'tong_dieu_chinh' => 0,
                'dieu_chinh_trung_binh' => 0,
                'error' => 'Không thể lấy dữ liệu khoản điều chỉnh lương lúc này.'
            ];
        }
    }

    /**
     * Lấy danh sách phòng ban cho filter
     *
     * @return array
     */
    public function getDepartments(): array
    {
        try {
            return DB::table('phong_ban')
                ->select('ma_pb', 'ten_pb')
                ->orderBy('ten_pb')
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            Log::error('[DashboardService] Lỗi lấy danh sách phòng ban: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Lấy danh sách chức vụ cho filter
     *
     * @return array
     */
    public function getPositions(): array
    {
        try {
            return DB::table('chuc_vu')
                ->select('ma_cv', 'ten_cv')
                ->orderBy('ten_cv')
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            Log::error('[DashboardService] Lỗi lấy danh sách chức vụ: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Lấy tổng số nhân viên
     *
     * @return int
     */
    public function getTotalEmployees(): int
    {
        try {
            return (int) DB::table('nhan_vien')
                ->whereNotIn('ma_tt', NhanVienStatus::terminalValues())
                ->count();
        } catch (\Exception $e) {
            Log::error('[DashboardService] Lỗi lấy tổng số nhân viên: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Lấy tổng số phòng ban
     *
     * @return int
     */
    public function getTotalDepartments(): int
    {
        try {
            return (int) DB::table('phong_ban')->count();
        } catch (\Exception $e) {
            Log::error('[DashboardService] Lỗi lấy tổng số phòng ban: ' . $e->getMessage());
            return 0;
        }
    }
}
