<?php

namespace App\Services;

use App\Repositories\LuongRepository;
use App\Support\JsonPaginator;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class LuongService
{
    protected $repository;

    public function __construct(LuongRepository $repository)
    {
        $this->repository = $repository;
    }

    public function getAll($filters = [])
    {
        try {
            $paginator = $this->repository->all($filters);

            return [
                'success' => true,
                'data' => JsonPaginator::from($paginator),
            ];
        } catch (Exception $e) {
            report($e);

            return [
                'success' => false,
                'message' => 'Không thể tải danh sách lương.',
            ];
        }
    }

    public function getById($id)
    {
        try {
            $record = $this->repository->find($id);

            if (!$record) {
                return [
                    'success' => false,
                    'message' => 'Không tìm thấy bản ghi',
                ];
            }

            return [
                'success' => true,
                'data' => $record,
            ];
        } catch (Exception $e) {
            report($e);

            return [
                'success' => false,
                'message' => 'Không thể tải bản ghi lương.',
            ];
        }
    }

    public function create(array $data)
    {
        try {
            $record = $this->repository->create($data);

            return [
                'success' => true,
                'message' => 'Tạo thành công',
                'data' => $record,
            ];
        } catch (Exception $e) {
            report($e);

            return [
                'success' => false,
                'message' => 'Không thể tạo bản ghi lương.',
            ];
        }
    }

    public function update($id, array $data)
    {
        try {
            $record = $this->repository->update($id, $data);

            if (!$record) {
                return [
                    'success' => false,
                    'message' => 'Không tìm thấy bản ghi',
                ];
            }

            return [
                'success' => true,
                'message' => 'Cập nhật thành công',
                'data' => $record,
            ];
        } catch (Exception $e) {
            report($e);

            return [
                'success' => false,
                'message' => 'Không thể cập nhật bản ghi lương.',
            ];
        }
    }

    public function delete($id)
    {
        try {
            $result = $this->repository->delete($id);

            if (!$result) {
                return [
                    'success' => false,
                    'message' => 'Không tìm thấy bản ghi',
                ];
            }

            return [
                'success' => true,
                'message' => 'Xóa thành công',
            ];
        } catch (Exception $e) {
            report($e);

            return [
                'success' => false,
                'message' => 'Không thể xóa bản ghi lương.',
            ];
        }
    }

    /**
     * =========================================================
     * XUẤT BÁO CÁO LƯƠNG THEO KỲ
     * =========================================================
     *
     * Input:
     * - $kyLuong: 2026-08 hoặc 2026-08-01
     *
     * Output:
     * [
     *     'success' => true,
     *     'data' => [
     *         'file_path' => '...',
     *         'filename' => 'BaoCaoLuong_08_2026.xlsx',
     *         'total' => 20,
     *     ]
     * ]
     */
    public function exportByKyLuong(
        string $kyLuong,
        array $filters = []
    ): array
    {
        try {
            $period = $this->normalizeKyLuong(
                $kyLuong
            );

            /*
             * Chuẩn hóa filter trước khi gọi Stored Procedure.
             */
            $normalizedFilters = [
                'tu_khoa' => $this->normalizeKeyword(
                    $filters['tu_khoa'] ?? null
                ),

                'ky_luong' => $period->format(
                    'Y-m-01'
                ),

                'ma_pb' => $this->normalizeNullableInt(
                    $filters['ma_pb'] ?? null
                ),

                'ma_cv' => $this->normalizeNullableInt(
                    $filters['ma_cv'] ?? null
                ),
            ];

            /*
             * SP giới hạn tối đa 100 bản ghi/page,
             * nên export phải loop toàn bộ page theo đúng filter.
             */
            $rows = $this->getAllSalaryRowsForExport(
                $normalizedFilters
            );

            if ($rows->isEmpty()) {
                return [
                    'success' => false,
                    'message' =>
                        'Không có dữ liệu lương phù hợp với bộ lọc.',
                ];
            }

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $sheet->setTitle(
                'BaoCaoLuong'
            );

            /*
             * =====================================================
             * TITLE
             * =====================================================
             */
            $title = sprintf(
                'BÁO CÁO LƯƠNG THÁNG %s',
                $period->format('m/Y')
            );

            $sheet->mergeCells(
                'A1:M1'
            );

            $sheet->setCellValue(
                'A1',
                $title
            );

            $sheet->getStyle('A1:M1')
                ->getFont()
                ->setBold(true)
                ->setSize(16);

            $sheet->getStyle('A1:M1')
                ->getAlignment()
                ->setHorizontal(
                    Alignment::HORIZONTAL_CENTER
                )
                ->setVertical(
                    Alignment::VERTICAL_CENTER
                );

            $sheet->getRowDimension(1)
                ->setRowHeight(28);

            /*
             * Row 2 để trống giống layout mẫu.
             */

            /*
             * =====================================================
             * HEADER
             * =====================================================
             */
            $headers = [
                'STT',
                'Mã NV',
                'Họ tên',
                'Phòng ban',
                'Chức vụ',
                'Ngày công',
                'Vào muộn',
                'Về sớm',
                'Thưởng',
                'Phạt',
                'Bảo hiểm',
                'Thuế',
                'Thực nhận',
            ];

            $sheet->fromArray(
                $headers,
                null,
                'A3'
            );

            $sheet->getStyle('A3:M3')
                ->getFont()
                ->setBold(true);

            $sheet->getStyle('A3:M3')
                ->getFill()
                ->setFillType(
                    Fill::FILL_SOLID
                )
                ->getStartColor()
                ->setARGB(
                    'FFE7E7E7'
                );

            $sheet->getStyle('A3:M3')
                ->getAlignment()
                ->setHorizontal(
                    Alignment::HORIZONTAL_CENTER
                )
                ->setVertical(
                    Alignment::VERTICAL_CENTER
                );

            $sheet->getRowDimension(3)
                ->setRowHeight(22);

            /*
             * =====================================================
             * DATA
             * =====================================================
             */
            $excelRow = 4;
            $index = 1;

            foreach ($rows as $row) {
                $sheet->setCellValue(
                    "A{$excelRow}",
                    $index
                );

                $sheet->setCellValueExplicit(
                    "B{$excelRow}",
                    (string) ($row->ma_nv ?? ''),
                    \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
                );

                $sheet->setCellValue(
                    "C{$excelRow}",
                    $row->ho_ten ?? ''
                );

                $sheet->setCellValue(
                    "D{$excelRow}",
                    $row->ten_pb ?? ''
                );

                $sheet->setCellValue(
                    "E{$excelRow}",
                    $row->ten_cv ?? ''
                );

                $sheet->setCellValue(
                    "F{$excelRow}",
                    (float) ($row->so_ngay_cham_cong ?? 0)
                );

                $sheet->setCellValue(
                    "G{$excelRow}",
                    (int) ($row->so_lan_vao_muon ?? 0)
                );

                $sheet->setCellValue(
                    "H{$excelRow}",
                    (int) ($row->so_lan_ve_som ?? 0)
                );

                $sheet->setCellValue(
                    "I{$excelRow}",
                    (float) ($row->thuong ?? 0)
                );

                $sheet->setCellValue(
                    "J{$excelRow}",
                    (float) ($row->phat ?? 0)
                );

                $sheet->setCellValue(
                    "K{$excelRow}",
                    (float) ($row->bao_hiem ?? 0)
                );

                $sheet->setCellValue(
                    "L{$excelRow}",
                    (float) ($row->thue ?? 0)
                );

                $sheet->setCellValue(
                    "M{$excelRow}",
                    (float) ($row->thuc_nhan ?? 0)
                );

                $excelRow++;
                $index++;
            }

            $lastRow = $excelRow - 1;

            /*
             * =====================================================
             * TABLE STYLE
             * =====================================================
             */
            $sheet->getStyle(
                "A3:M{$lastRow}"
            )
                ->getBorders()
                ->getAllBorders()
                ->setBorderStyle(
                    Border::BORDER_THIN
                )
                ->getColor()
                ->setARGB(
                    'FF7F7F7F'
                );

            $sheet->getStyle(
                "A4:M{$lastRow}"
            )
                ->getAlignment()
                ->setVertical(
                    Alignment::VERTICAL_CENTER
                );

            /*
             * Căn giữa các cột số lượng/thống kê.
             */
            $sheet->getStyle(
                "A4:A{$lastRow}"
            )
                ->getAlignment()
                ->setHorizontal(
                    Alignment::HORIZONTAL_CENTER
                );

            $sheet->getStyle(
                "F4:H{$lastRow}"
            )
                ->getAlignment()
                ->setHorizontal(
                    Alignment::HORIZONTAL_CENTER
                );

            /*
             * Format ngày công.
             */
            $sheet->getStyle(
                "F4:F{$lastRow}"
            )
                ->getNumberFormat()
                ->setFormatCode(
                    '0.##'
                );

            /*
             * Format tiền.
             */
            $sheet->getStyle(
                "I4:M{$lastRow}"
            )
                ->getNumberFormat()
                ->setFormatCode(
                    '#,##0'
                );

            /*
             * Thực nhận in đậm.
             */
            $sheet->getStyle(
                "M4:M{$lastRow}"
            )
                ->getFont()
                ->setBold(true);

            /*
             * =====================================================
             * COLUMN WIDTH
             * =====================================================
             */
            $columnWidths = [
                'A' => 7,
                'B' => 12,
                'C' => 24,
                'D' => 22,
                'E' => 20,
                'F' => 12,
                'G' => 12,
                'H' => 12,
                'I' => 15,
                'J' => 15,
                'K' => 15,
                'L' => 15,
                'M' => 18,
            ];

            foreach ($columnWidths as $column => $width) {
                $sheet->getColumnDimension(
                    $column
                )->setWidth(
                    $width
                );
            }

            /*
             * Header luôn hiển thị khi scroll.
             */
            $sheet->freezePane(
                'A4'
            );

            $sheet->setAutoFilter(
                "A3:M{$lastRow}"
            );

            /*
             * Print setup.
             */
            $sheet->getPageSetup()
                ->setFitToWidth(1)
                ->setFitToHeight(0);

            $sheet->getPageMargins()
                ->setTop(0.4)
                ->setRight(0.3)
                ->setLeft(0.3)
                ->setBottom(0.4);

            /*
             * =====================================================
             * SAVE FILE
             * =====================================================
             */
            $directory = storage_path(
                'app/temp/luong'
            );

            File::ensureDirectoryExists(
                $directory
            );

            $filename = sprintf(
                'BaoCaoLuong_%s.xlsx',
                $period->format('m_Y')
            );

            $filePath =
                $directory.
                DIRECTORY_SEPARATOR.
                $filename;

            (new Xlsx($spreadsheet))
                ->save(
                    $filePath
                );

            $spreadsheet
                ->disconnectWorksheets();

            return [
                'success' => true,
                'message' => 'Xuất báo cáo lương thành công.',
                'data' => [
                    'file_path' => $filePath,
                    'filename' => $filename,
                    'total' => $rows->count(),
                    'ky_luong' => $period->format('Y-m-01'),
                    'filters' => $normalizedFilters,
                ],
            ];
        } catch (\Throwable $e) {
            report($e);

            return [
                'success' => false,
                'message' => 'Không thể xuất báo cáo lương.',
            ];
        }
    }

    /**
     * Chuẩn hóa:
     * - 2026-08
     * - 2026-08-01
     *
     * về ngày đầu tháng.
     */
    private function normalizeKyLuong(
        string $kyLuong
    ): Carbon {
        $kyLuong = trim(
            $kyLuong
        );

        if ($kyLuong === '') {
            throw new InvalidArgumentException(
                'Kỳ lương không được để trống.'
            );
        }

        $formats = [
            'Y-m-d',
            'Y-m',
        ];

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat(
                    '!'.$format,
                    $kyLuong
                );

                if (
                    $date !== false
                    && $date->format($format) === $kyLuong
                ) {
                    return $date->startOfMonth();
                }
            } catch (\Throwable) {
                // thử format tiếp theo
            }
        }

        throw new InvalidArgumentException(
            'Kỳ lương không hợp lệ. Dùng YYYY-MM hoặc YYYY-MM-DD.'
        );
    }

    /**
     * Lấy toàn bộ dữ liệu lương theo filter.
     *
     * Repository pagination is deliberately reused here so exports remain
     * available on installations that do not ship the legacy procedure.
     */
    private function getAllSalaryRowsForExport(
        array $filters
    ): Collection {
        $allRows = collect();
        $page = 1;
        $perPage = 50;

        $queryFilters = array_filter([
            'tu_khoa' => $filters['tu_khoa'] ?? null,
            'ky_luong' => $filters['ky_luong'] ?? null,
            'ma_pb' => $filters['ma_pb'] ?? null,
            'ma_cv' => $filters['ma_cv'] ?? null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        do {
            $paginator = $this->repository->all($queryFilters + [
                'page' => $page,
                'per_page' => $perPage,
            ]);
            $rows = collect($paginator->items());
            $allRows = $allRows->concat($rows);

            $page++;
        } while ($rows->isNotEmpty() && $paginator->currentPage() < $paginator->lastPage());

        return $allRows->values();
    }

    /**
     * Chuẩn hóa keyword:
     * null / '' -> null.
     */
    private function normalizeKeyword(
        mixed $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim(
            (string) $value
        );

        return $value === ''
            ? null
            : $value;
    }

    /**
     * Chuẩn hóa filter số nguyên nullable.
     *
     * null / '' / invalid -> null.
     */
    private function normalizeNullableInt(
        mixed $value
    ): ?int {
        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }

        return is_numeric($value)
            ? (int) $value
            : null;
    }
}
