<?php

namespace App\Services;

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
            $today = now()->toDateString();
            $expiryDate = now()->addDays($days)->toDateString();

            $result = DB::table('hop_dong as hd')
                ->join('nhan_vien as nv', 'hd.ma_nv', '=', 'nv.ma_nv')
                ->join('loai_hop_dong as lhd', 'hd.ma_lhd', '=', 'lhd.ma_lhd')
                ->select([
                    'nv.ho_ten',
                    'nv.ma_nv',
                    'lhd.ten_loai_hop_dong',
                    'hd.ngay_bat_dau',
                    'hd.ngay_ket_thuc',
                    DB::raw('DATEDIFF(hd.ngay_ket_thuc, CURDATE()) as so_ngay_con_lai')
                ])
                ->where('hd.ngay_ket_thuc', '>=', $today)
                ->where('hd.ngay_ket_thuc', '<=', $expiryDate)
                ->where('nv.ma_tt', '<>', 4) // Không lấy nhân viên đã nghỉ
                ->orderBy('so_ngay_con_lai', 'ASC')
                ->limit(10)
                ->get()
                ->toArray();

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
     * Báo cáo lương cho tháng hiện tại
     * Sử dụng Query Builder vì không có procedure sp_luong_tim_kiem_phan_trang
     * 
     * @return array
     */
    public function getSalaryReport(): array
    {
        try {
            $month = (int) now()->month;
            $year = (int) now()->year;
            $kyLuong = sprintf('%d-%02d', $year, $month);

            // Kiểm tra xem bảng luong có dữ liệu không
            $checkData = DB::table('luong')
                ->where('ky_luong', $kyLuong)
                ->exists();

            if (!$checkData) {
                return [
                    'thang' => $month,
                    'nam' => $year,
                    'so_nguoi' => 0,
                    'tong_luong' => 0,
                    'luong_trung_binh' => 0,
                    'error' => 'Chưa có dữ liệu lương cho tháng ' . $month . '/' . $year
                ];
            }

            $data = DB::table('luong as l')
                ->join('nhan_vien as nv', 'l.ma_nv', '=', 'nv.ma_nv')
                ->where('l.ky_luong', $kyLuong)
                ->select([
                    DB::raw('COUNT(l.ma_luong) as so_nguoi'),
                    DB::raw('SUM(l.luong_net) as tong_luong'),
                    DB::raw('ROUND(AVG(l.luong_net), 0) as luong_trung_binh')
                ])
                ->first();

            if (!$data || (int) $data->so_nguoi === 0) {
                return [
                    'thang' => $month,
                    'nam' => $year,
                    'so_nguoi' => 0,
                    'tong_luong' => 0,
                    'luong_trung_binh' => 0,
                    'error' => 'Chưa có dữ liệu lương cho tháng ' . $month . '/' . $year
                ];
            }

            return [
                'thang' => $month,
                'nam' => $year,
                'so_nguoi' => (int) $data->so_nguoi,
                'tong_luong' => (float) $data->tong_luong,
                'luong_trung_binh' => (float) $data->luong_trung_binh,
            ];

        } catch (\Exception $e) {
            Log::error('[DashboardService] Lỗi lấy báo cáo lương: ' . $e->getMessage());
            return [
                'thang' => (int) now()->month,
                'nam' => (int) now()->year,
                'so_nguoi' => 0,
                'tong_luong' => 0,
                'luong_trung_binh' => 0,
                'error' => 'Không thể lấy dữ liệu lương: ' . $e->getMessage()
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
                ->where('ma_tt', '<>', 4) // Không tính nhân viên đã nghỉ
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