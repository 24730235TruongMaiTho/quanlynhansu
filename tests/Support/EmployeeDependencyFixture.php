<?php

namespace Tests\Support;

use PDO;
use RuntimeException;

final class EmployeeDependencyFixture
{
    private const DEPENDENCIES = [
        'hop_dong',
        'cham_cong',
        'nghi_phep',
        'luong',
        'lich_su_he_so_luong',
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<string> */
    public static function dependencyNames(): array
    {
        return self::DEPENDENCIES;
    }

    public function add(string $maNv, string $dependency): void
    {
        if (! in_array($dependency, self::DEPENDENCIES, true)) {
            throw new RuntimeException('Unknown employee dependency fixture.');
        }

        switch ($dependency) {
            case 'hop_dong':
                $this->pdo->exec("INSERT INTO loai_hop_dong (ten_lhd) VALUES ('Fixture contract')");
                $type = (int) $this->pdo->lastInsertId();
                $statement = $this->pdo->prepare(
                    'INSERT INTO hop_dong (ma_nv, ma_lhd, ngay_ky, ngay_het_han, luong_co_ban)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $statement->execute([$maNv, $type, '2026-01-01', null, 10000000]);
                break;
            case 'cham_cong':
                $statement = $this->pdo->prepare(
                    'INSERT INTO cham_cong (ma_nv, ngay_lam, so_gio_lam, vao_muon, ve_som)
                     VALUES (?, ?, ?, b\'0\', b\'0\')'
                );
                $statement->execute([$maNv, '2026-08-18', 8]);
                break;
            case 'nghi_phep':
                $this->pdo->exec("INSERT INTO loai_phep (ten_lp) VALUES ('Fixture leave')");
                $type = (int) $this->pdo->lastInsertId();
                $statement = $this->pdo->prepare(
                    'INSERT INTO nghi_phep (ma_nv, tu_ngay, den_ngay, ma_lp, ly_do, trang_thai_duyet)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $statement->execute([$maNv, '2026-08-18', '2026-08-18', $type, 'Fixture', 0]);
                break;
            case 'luong':
                $statement = $this->pdo->prepare(
                    'INSERT INTO luong (ma_nv, ky_luong, thuong, phat, bao_hiem, thue)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $statement->execute([$maNv, '2026-08-01', 0, 0, 0, 0]);
                break;
            case 'lich_su_he_so_luong':
                $statement = $this->pdo->prepare(
                    'INSERT INTO lich_su_he_so_luong (ma_nv, he_so_luong, tu_ngay, den_ngay)
                     VALUES (?, ?, ?, ?)'
                );
                $statement->execute([$maNv, 1.00, '2026-01-01', '2026-12-31']);
                break;
        }
    }

    public function clear(string $maNv, string $dependency): void
    {
        if (! in_array($dependency, self::DEPENDENCIES, true)) {
            throw new RuntimeException('Unknown employee dependency fixture.');
        }

        $this->pdo->prepare("DELETE FROM {$dependency} WHERE ma_nv = ?")->execute([$maNv]);
    }
}
