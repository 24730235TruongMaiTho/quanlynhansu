<?php

namespace App\Http\Controllers\Backend;

use Illuminate\Database\DatabaseManager;
use Illuminate\Routing\Controller;

class QuyenController extends Controller
{
    public function __construct(
        private DatabaseManager $database,
    ) {}

    /**
     * @return list<string>
     */
    public function LayKyHieuQuyen(string $maNv): array
    {
        $rows = $this->database->connection()->select(
            'CALL sp_quyen_lay_theo_ma_nhan_vien(?)',
            [$maNv],
        );

        return array_values(array_filter(array_map(
            static fn (object $row): ?string => isset($row->ky_hieu_quyen)
                ? (string) $row->ky_hieu_quyen
                : null,
            $rows,
        )));
    }
}