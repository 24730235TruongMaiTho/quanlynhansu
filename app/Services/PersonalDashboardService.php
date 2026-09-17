<?php

namespace App\Services;

use App\Models\NhanVien;
use App\Support\CurrentEmployee;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tổng hợp năm widget tự phục vụ của đúng nhân viên đang đăng nhập.
 *
 * Mỗi widget được đọc độc lập để một bảng lỗi hoặc thiếu trong môi trường
 * chưa hoàn chỉnh không làm mất toàn bộ dashboard của nhân viên.
 */
final class PersonalDashboardService
{
    public function __construct(private CurrentEmployee $currentEmployee) {}

    /** @return array<string, array<string, mixed>> */
    public function getOverview(NhanVien $actor): array
    {
        $maNv = $this->currentEmployee->id($actor);

        return [
            'profile' => $this->widget('profile', fn (): ?array => $this->profile($actor)),
            'attendance' => $this->widget('attendance', fn (): ?array => $this->attendance($maNv)),
            'leave' => $this->widget('leave', fn (): ?array => $this->leave($maNv)),
            'contract' => $this->widget('contract', fn (): ?array => $this->contract($maNv)),
            'salary' => $this->widget('salary', fn (): ?array => $this->salary($maNv)),
        ];
    }

    /** @return array{state: string, data?: mixed, message?: string} */
    private function widget(string $name, callable $loader): array
    {
        try {
            $data = $loader();

            return $data === null
                ? ['state' => 'empty']
                : ['state' => 'ready', 'data' => $data];
        } catch (Throwable $exception) {
            Log::warning('personal_dashboard_widget_failed', [
                'widget' => $name,
                'exception_class' => $exception::class,
            ]);

            return [
                'state' => 'error',
                'message' => 'Không thể tải dữ liệu lúc này.',
            ];
        }
    }

    /** @return array{ma_nv: string, ho_ten: string, vai_tro: string}|null */
    private function profile(NhanVien $actor): ?array
    {
        $maNv = (string) $actor->getAuthIdentifier();
        $hoTen = trim((string) ($actor->ho_ten ?? ''));

        if ($maNv === '' || $hoTen === '') {
            return null;
        }

        $role = trim((string) ($actor->ten_vt ?? ''));
        if ($role === '') {
            $role = 'Vai trò #'.(int) ($actor->ma_vt ?? 0);
        }

        return [
            'ma_nv' => $maNv,
            'ho_ten' => $hoTen,
            'vai_tro' => $role,
        ];
    }

    /** @return array{thang: int, nam: int, so_ngay: int, tong_gio: int, so_lan_vao_muon: int, so_lan_ve_som: int}|null */
    private function attendance(string $maNv): ?array
    {
        $now = CarbonImmutable::now();
        $row = DB::table('cham_cong')
            ->where('ma_nv', $maNv)
            ->whereBetween('ngay_lam', [$now->startOfMonth()->toDateString(), $now->endOfMonth()->toDateString()])
            ->selectRaw('COUNT(*) AS so_ngay')
            ->selectRaw('COALESCE(SUM(so_gio_lam), 0) AS tong_gio')
            ->selectRaw('COALESCE(SUM(CASE WHEN vao_muon = 1 THEN 1 ELSE 0 END), 0) AS so_lan_vao_muon')
            ->selectRaw('COALESCE(SUM(CASE WHEN ve_som = 1 THEN 1 ELSE 0 END), 0) AS so_lan_ve_som')
            ->first();

        $days = (int) ($row->so_ngay ?? 0);
        if ($days === 0) {
            return null;
        }

        return [
            'thang' => $now->month,
            'nam' => $now->year,
            'so_ngay' => $days,
            'tong_gio' => (int) ($row->tong_gio ?? 0),
            'so_lan_vao_muon' => (int) ($row->so_lan_vao_muon ?? 0),
            'so_lan_ve_som' => (int) ($row->so_lan_ve_som ?? 0),
        ];
    }

    /** @return array{so_don_cho: int, don_gan_nhat: ?array<string, mixed>}|null */
    private function leave(string $maNv): ?array
    {
        $pending = (int) DB::table('nghi_phep')
            ->where('ma_nv', $maNv)
            ->where('trang_thai_duyet', 0)
            ->count();

        $latest = DB::table('nghi_phep as np')
            ->leftJoin('loai_phep as lp', 'lp.ma_lp', '=', 'np.ma_lp')
            ->where('np.ma_nv', $maNv)
            ->orderByDesc('np.ma_np')
            ->select([
                'np.ma_np',
                'np.tu_ngay',
                'np.den_ngay',
                'np.trang_thai_duyet',
                'lp.ten_lp',
            ])
            ->first();

        if ($pending === 0 && $latest === null) {
            return null;
        }

        return [
            'so_don_cho' => $pending,
            'don_gan_nhat' => $latest === null ? null : [
                'ma_np' => (int) $latest->ma_np,
                'tu_ngay' => (string) $latest->tu_ngay,
                'den_ngay' => (string) $latest->den_ngay,
                'loai_phep' => (string) ($latest->ten_lp ?? ''),
                'trang_thai' => $this->leaveStatus((int) $latest->trang_thai_duyet),
            ],
        ];
    }

    /** @return array{loai_hop_dong: string, ngay_ky: string, ngay_het_han: ?string}|null */
    private function contract(string $maNv): ?array
    {
        $today = CarbonImmutable::now()->toDateString();
        $row = DB::table('hop_dong as hd')
            ->leftJoin('loai_hop_dong as lhd', 'lhd.ma_lhd', '=', 'hd.ma_lhd')
            ->where('hd.ma_nv', $maNv)
            ->whereDate('hd.ngay_ky', '<=', $today)
            ->where(function ($query) use ($today): void {
                $query->whereNull('hd.ngay_het_han')
                    ->orWhereDate('hd.ngay_het_han', '>=', $today);
            })
            ->orderByDesc('hd.ngay_ky')
            ->orderByDesc('hd.ma_hd')
            ->select(['hd.ngay_ky', 'hd.ngay_het_han', 'lhd.ten_lhd'])
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'loai_hop_dong' => (string) ($row->ten_lhd ?? ''),
            'ngay_ky' => (string) $row->ngay_ky,
            'ngay_het_han' => $row->ngay_het_han === null ? null : (string) $row->ngay_het_han,
        ];
    }

    /** @return array{ky_luong: string, trang_thai: string}|null */
    private function salary(string $maNv): ?array
    {
        $row = DB::table('luong')
            ->where('ma_nv', $maNv)
            ->orderByDesc('ky_luong')
            ->orderByDesc('ma_luong')
            ->select(['ky_luong'])
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'ky_luong' => (string) $row->ky_luong,
            'trang_thai' => 'Đã ghi nhận',
        ];
    }

    private function leaveStatus(int $status): string
    {
        return match ($status) {
            0 => 'Chờ duyệt',
            1 => 'Đã duyệt',
            2 => 'Từ chối',
            default => 'Chưa xác định',
        };
    }
}
