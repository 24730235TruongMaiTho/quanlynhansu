<?php

namespace App\Repositories;

use App\Contracts\HopDongRepositoryContract;
use App\Exceptions\HopDongDomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\DatabaseManager;

final class HopDongRepository implements HopDongRepositoryContract
{
    public function __construct(private DatabaseManager $database) {}

    public function paginate(array $filters, int $perPage, int $warningDays): LengthAwarePaginator
    {
        $today = now()->toDateString();
        $warningEnd = now()->addDays($warningDays)->toDateString();

        return $this->database->connection()->table('hop_dong as hd')
            ->join('nhan_vien as nv', 'nv.ma_nv', '=', 'hd.ma_nv')
            ->join('loai_hop_dong as lhd', 'lhd.ma_lhd', '=', 'hd.ma_lhd')
            ->when(($filters['keyword'] ?? '') !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('nv.ma_nv', 'like', '%'.$filters['keyword'].'%')
                ->orWhere('nv.ho_ten', 'like', '%'.$filters['keyword'].'%')))
            ->when(($filters['ma_lhd'] ?? null) !== null, fn ($q) => $q->where('hd.ma_lhd', $filters['ma_lhd']))
            ->when(($filters['sap_het_han'] ?? false) === true, fn ($q) => $q->whereBetween('hd.ngay_het_han', [$today, $warningEnd]))
            ->select(['hd.ma_hd', 'hd.ma_nv', 'nv.ho_ten', 'hd.ma_lhd', 'lhd.ten_lhd', 'hd.ngay_ky', 'hd.ngay_het_han', 'hd.luong_co_ban'])
            ->selectRaw('CASE WHEN hd.ngay_het_han BETWEEN ? AND ? THEN 1 ELSE 0 END AS sap_het_han', [$today, $warningEnd])
            ->orderByDesc('hd.ma_hd')->paginate($perPage)->withQueryString();
    }

    public function find(int $maHd): ?object
    {
        return $this->database->connection()->table('hop_dong')->where('ma_hd', $maHd)
            ->first(['ma_hd', 'ma_nv', 'ma_lhd', 'ngay_ky', 'ngay_het_han', 'luong_co_ban']);
    }

    public function employees(): array
    {
        return $this->database->connection()->table('nhan_vien')->orderBy('ma_nv')->get(['ma_nv', 'ho_ten'])->all();
    }

    public function types(): array
    {
        return $this->database->connection()->table('loai_hop_dong')->orderBy('ma_lhd')->get(['ma_lhd', 'ten_lhd'])->all();
    }

    public function create(array $data): int
    {
        return (int) $this->database->connection()->table('hop_dong')->insertGetId($data, 'ma_hd');
    }

    public function update(int $maHd, array $data): void
    {
        if ($this->database->connection()->table('hop_dong')->where('ma_hd', $maHd)->update($data) === 0 && $this->find($maHd) === null) {
            throw new HopDongDomainException('Không tìm thấy hợp đồng.', 'HD_NOT_FOUND');
        }
    }

    public function delete(int $maHd): void
    {
        if ($this->database->connection()->table('hop_dong')->where('ma_hd', $maHd)->delete() !== 1) {
            throw new HopDongDomainException('Không tìm thấy hợp đồng.', 'HD_NOT_FOUND');
        }
    }
}
