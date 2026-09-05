<?php

namespace App\Http\Controllers\Backend;

use App\Http\Requests\BatchSaveChamCongRequest;
use App\Http\Requests\UpdateChamCongRequest;
use App\Contracts\NhanVienServiceContract;
use App\Services\ChamCongService;
use App\Services\ChamCongExportService;
use App\Services\ChamCongImportService;
use App\Support\JsonPaginator;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ChamCongController extends Controller
{
    public function __construct(
        private ChamCongService $chamCongService,
        private NhanVienServiceContract $nhanVienService
    )
    {
    }

    /**
     * =========================================================
     * DANH SÁCH NHÂN VIÊN + TỔNG HỢP CHẤM CÔNG
     * =========================================================
     *
     * GET /api/v1/cham-cong/nhan-vien
     *
     * Query:
     *
     * tu_khoa
     * ma_pb
     * thang
     * nam
     * page
     * per_page
     */
    public function employees(
        Request $request
    ): JsonResponse {
        try {
            $validated = $request->validate([
                'tu_khoa' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'ma_pb' => [
                    'nullable',
                    'integer',
                    'exists:phong_ban,ma_pb',
                ],

                'thang' => [
                    'nullable',
                    'integer',
                    'between:1,12',
                ],

                'nam' => [
                    'nullable',
                    'integer',
                    'between:2000,2100',
                ],

                'page' => [
                    'nullable',
                    'integer',
                    'min:1',
                ],

                'per_page' => ['nullable', 'integer'],
            ]);

            $filters = [
                'tu_khoa' => $this->nullIfEmpty($validated['tu_khoa'] ?? null),
                'ma_pb' => isset($validated['ma_pb']) ? (int) $validated['ma_pb'] : null,
                'thang' => (int) ($validated['thang'] ?? now()->month),
                'nam' => (int) ($validated['nam'] ?? now()->year),
                'page' => (int) ($validated['page'] ?? 1),
                'so_dong' => $this->pageSize($validated['per_page'] ?? null),
            ];

            $paginator = $this->nhanVienService->paginateForAttendance($filters);

            return response()->json([
                'success' => true,
                'data' => JsonPaginator::from($paginator),
            ]);

        } catch (ValidationException $exception) {
            throw $exception;
        } catch (QueryException $exception) {

            return $this->queryError(
                $exception,
                'Không thể tải danh sách nhân viên.',
                500
            );

        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Không thể tải danh sách nhân viên.',
            ], 500);
        }
    }

    private function pageSize(mixed $value): int
    {
        return in_array((int) $value, [10, 20, 25, 50], true)
            ? (int) $value
            : 10;
    }

    /**
     * =========================================================
     * CHI TIẾT CHẤM CÔNG CỦA NHÂN VIÊN
     * =========================================================
     *
     * GET /api/v1/cham-cong
     *
     * Tham số mã nhân viên: 00001
     * &thang=8
     * &nam=2026
     * &page=1
     * &per_page=31 (full calendar month; visible table uses 10/20/50)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
            'ma_nv' => [
                'required',
                'string',
                'max:5',
                'exists:nhan_vien,ma_nv',
            ],
            'thang' => [
                'required',
                'integer',
                'between:1,12',
            ],
            'nam' => [
                'required',
                'integer',
                'between:2000,2100',
            ],
            'page' => [
                'nullable',
                'integer',
                'min:1',
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
            ]);

            $maNhanVien = $validated['ma_nv'];
            $thang = (int) $validated['thang'];
            $nam = (int) $validated['nam'];

            // The detail endpoint remains permissive for existing callers;
            // the calendar UI requests 31 while its visible selector uses
            // the shared 10/20/50 sizes.
            $perPage = min(
                100,
                max(1, (int) ($validated['per_page'] ?? 31))
            );

            $paginator = DB::table('cham_cong')
                ->where(
                    'ma_nv',
                    $maNhanVien
                )
                ->whereYear(
                    'ngay_lam',
                    $nam
                )
                ->whereMonth(
                    'ngay_lam',
                    $thang
                )
                ->select([
                    'ma_cc',
                    'ma_nv',
                    'ngay_lam',
                    'so_gio_lam',
                    'vao_muon',
                    've_som',
                ])
                ->orderBy('ngay_lam')
                ->paginate($perPage)
                ->withQueryString();

            $summary = DB::table('cham_cong')
                ->where(
                    'ma_nv',
                    $maNhanVien
                )
                ->whereYear(
                    'ngay_lam',
                    $nam
                )
                ->whereMonth(
                    'ngay_lam',
                    $thang
                )
                ->selectRaw(
                    '
                COALESCE(SUM(so_gio_lam), 0)
                    AS tong_gio_lam,

                COALESCE(
                    SUM(
                        CASE
                            WHEN vao_muon = 1
                            THEN 1
                            ELSE 0
                        END
                    ),
                    0
                ) AS so_lan_vao_muon,

                COALESCE(
                    SUM(
                        CASE
                            WHEN ve_som = 1
                            THEN 1
                            ELSE 0
                        END
                    ),
                    0
                ) AS so_lan_ve_som,

                COALESCE(
                    SUM(
                        CASE
                            WHEN so_gio_lam >= 8
                                THEN 1
                            WHEN so_gio_lam >= 4
                                THEN 0.5
                            ELSE 0
                        END
                    ),
                    0
                ) AS so_ngay_cham_cong
                '
                )
                ->first();

            return response()->json([
                'success' => true,

                'data' => JsonPaginator::from($paginator),

                'summary' => [
                    'tong_gio_lam' => (float) ($summary->tong_gio_lam ?? 0),

                    'so_lan_vao_muon' => (int) ($summary->so_lan_vao_muon ?? 0),

                    'so_lan_ve_som' => (int) ($summary->so_lan_ve_som ?? 0),

                    'so_ngay_cham_cong' => (float) ($summary->so_ngay_cham_cong ?? 0),
                ],
            ]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (QueryException $exception) {
            return $this->queryError(
                $exception,
                'Không thể tải dữ liệu chấm công.'
            );
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Đã xảy ra lỗi khi tải dữ liệu chấm công.',
            ], 500);
        }
    }

    /**
     * =========================================================
     * DANH SÁCH PHÒNG BAN
     * =========================================================
     *
     * GET /api/v1/cham-cong/phong-ban
     */
    public function phongBan(): JsonResponse
    {
        try {
            $rows = DB::table('phong_ban')
                ->select(['ma_pb', 'ten_pb'])
                ->orderBy('ma_pb')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $rows,
            ]);

        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Không thể tải danh sách phòng ban.',
            ], 500);
        }
    }

    /**
     * =========================================================
     * CẬP NHẬT CHẤM CÔNG
     * =========================================================
     *
     * PUT/PATCH /api/v1/cham-cong/{ma_cc}
     *
     * Body:
     *
     * {
     *     "so_gio_lam": 8,
     *     "vao_muon": 0,
     *     "ve_som": 0
     * }
     */
    public function update(
        UpdateChamCongRequest $request,
        int $cham_cong
    ): JsonResponse {
        try {
            $validated = $request->validated();

            $updated = DB::transaction(function () use ($cham_cong, $validated) {
                $attendance = DB::table('cham_cong')
                    ->where('ma_cc', $cham_cong)
                    ->lockForUpdate()
                    ->first([
                        'ma_cc',
                        'ma_nv',
                        'ngay_lam',
                        'so_gio_lam',
                        'vao_muon',
                        've_som',
                    ]);

                if (! $attendance) {
                    return null;
                }

                DB::table('cham_cong')
                    ->where('ma_cc', $cham_cong)
                    ->update([
                        'so_gio_lam' => $validated['so_gio_lam'],
                        'vao_muon' => (int) $validated['vao_muon'],
                        've_som' => (int) $validated['ve_som'],
                    ]);

                return DB::table('cham_cong')
                    ->where('ma_cc', $cham_cong)
                    ->first([
                        'ma_cc',
                        'ma_nv',
                        'ngay_lam',
                        'so_gio_lam',
                        'vao_muon',
                        've_som',
                    ]);
            });

            if ($updated === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy dữ liệu chấm công.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật chấm công thành công.',
                'data' => $updated,
            ]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Không thể cập nhật chấm công.',
            ], 500);
        }
    }

    /**
     * =========================================================
     * XUẤT BẢNG CHẤM CÔNG SANG EXCEL
     * =========================================================
     *
     * GET /api/v1/cham-cong/export
     *
     * ?thang=8&nam=2026&format=xlsx
     */
    public function export(Request $request, ChamCongExportService $exportService)
    {
        try {
            $validated = $request->validate([
                'thang' => ['required', 'integer', 'between:1,12'],
                'nam' => ['required', 'integer', 'between:2000,2100'],
                'format' => ['nullable', 'in:xlsx,csv'],
            ]);

            $month = (int) $validated['thang'];
            $year = (int) $validated['nam'];
            $format = $validated['format'] ?? 'xlsx';

            // Export file
            $filePath = $format === 'csv'
                ? $exportService->exportToCSV($month, $year)
                : $exportService->exportToExcel($month, $year);

            $filename = "cham_cong_{$month}_{$year}." . $format;

            return response()->download(
                $filePath,
                $filename,
                [
                    'Content-Type' => $format === 'csv'
                        ? 'text/csv; charset=utf-8'
                        : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ]
            );

        } catch (QueryException $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Không thể xuất dữ liệu chấm công.',
            ], 422);

        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Không thể xuất dữ liệu chấm công.',
            ], 500);
        }
    }

    /**
     * =========================================================
     * NHẬP BẢNG CHẤM CÔNG TỪ FILE
     * =========================================================
     *
     * POST /api/v1/cham-cong/import
     *
     * Body: multipart/form-data
     * - file: CSV/Excel file
     *
     * Định dạng CSV:
     * ma_nv,ngay_lam,so_gio_lam,vao_muon,ve_som
     * 00001,01/08/2026,8,0,0
     */
    public function import(Request $request, ChamCongImportService $importService): JsonResponse
    {
        try {
            $validated = $request->validate([
                'file' => ['required', 'file', 'mimes:csv,xlsx,xls'],
            ]);

            $file = $validated['file'];
            $result = $importService->import($file);

            $statusCode = $result['success'] ? 200 : 422;

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data' => $result['data'],
                'errors' => $result['errors'],
            ], $statusCode);

        } catch (ValidationException $exception) {
            throw $exception;

        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Không thể nhập file chấm công.',
                'data' => [],
                'errors' => [],
            ], 500);
        }
    }

    /**
     * =========================================================
     * XUẤT FILE TEMPLATE NHẬP BẢNG CHẤM CÔNG
     * =========================================================
     *
     * GET /api/v1/cham-cong/template
     *
     * Query parameters:
     * - format: xlsx|csv
     *
     * Ví dụ:
     * GET /api/v1/cham-cong/template?format=xlsx
     *
     * Template:
     * ma_nv,ngay_lam,so_gio_lam,vao_muon,ve_som
     *
     * 00001,01/08/2026,8,0,0
     */
    public function exportImportTemplate(
        Request $request,
        ChamCongImportService $importService
    ): BinaryFileResponse | JsonResponse
    {
        try {
            $validated = $request->validate([
                'format' => [
                    'nullable',
                    'in:xlsx,csv',
                ],
            ]);

            $format =
                $validated['format']
                ?? 'xlsx';

            $filePath =
                $importService->exportTemplate(
                    $format
                );

            $filename =
                "mau_import_cham_cong.{$format}";

            return response()
                ->download(
                    $filePath,
                    $filename,
                    [
                        'Content-Type' =>
                            $format === 'csv'
                                ? 'text/csv; charset=utf-8'
                                : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ]
                )
                ->deleteFileAfterSend(true);

        } catch (ValidationException $exception) {
            throw $exception;

        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Không thể tạo file mẫu chấm công.',
                'data' => [],
                'errors' => [],
            ], 500);
        }
    }

    public function batchSave(
        BatchSaveChamCongRequest $request
    ): \Illuminate\Http\JsonResponse {
        $result =
            $this->chamCongService
                ->saveBatchAttendance(
                    $request->validated()
                );

        if (! $result['success']) {
            return response()->json(
                $result,
                422
            );
        }

        return response()->json(
            $result
        );
    }


    // ...existing code...
    private function nullIfEmpty(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value) && trim($value) === '') {
            return null;
        }

        return is_string($value) ? trim($value) : $value;
    }

    /**
     * Trả message SIGNAL của MariaDB/MySQL.
     */
    private function queryError(QueryException $exception, string $fallback, int $status = 422): JsonResponse
    {
        report($exception);

        return response()->json([
            'success' => false,
            'message' => $fallback,
        ], $status);
    }



    /**
     * =========================================================
     * XÓA CHẤM CÔNG
     * =========================================================
     *
     * DELETE /api/v1/cham-cong/{ma_cc}
     */
    public function destroy(int $cham_cong): JsonResponse
    {
        try {
            $result = $this->chamCongService->delete($cham_cong);

            if (!$result['success']) {
                return response()->json($result, 404);
            }

            return response()->json($result);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Không thể xóa dữ liệu chấm công.',
            ], 500);
        }
    }
}
