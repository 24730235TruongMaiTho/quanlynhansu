<?php

namespace App\Http\Controllers\Backend;

use App\Contracts\NhanVienServiceContract;
use App\Support\NhanVienDepartmentScope;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChamCongController extends Controller
{
    public function __construct(
        private NhanVienServiceContract $nhanVienService,
        private NhanVienDepartmentScope $departmentScope,
    ) {}

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
    public function employees(Request $request): JsonResponse
    {
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

                'per_page' => [
                    'nullable',
                    'integer',
                    'min:1',
                    'max:100',
                ],
            ]);

            $filters = [
                'tu_khoa' => $this->nullIfEmpty($validated['tu_khoa'] ?? null),
                'ma_pb' => isset($validated['ma_pb']) ? (int) $validated['ma_pb'] : null,
                'thang' => (int) ($validated['thang'] ?? now()->month),
                'nam' => (int) ($validated['nam'] ?? now()->year),
                'page' => (int) ($validated['page'] ?? 1),
                'so_dong' => (int) ($validated['per_page'] ?? 15),
            ];

            $actor = $request->user();
            $filters = $this->departmentScope->constrainFilters($filters, $actor);

            $paginator = $this->nhanVienService->paginateForAttendance($filters);
            $query = $request->query();
            if (array_key_exists('ma_pb', $query) || $this->departmentScope->isDepartmentManager($actor)) {
                $query['ma_pb'] = $filters['ma_pb'];
            }
            $paginator->withPath($request->url())->appends($query);

            return response()->json([
                'success' => true,
                'data' => $paginator,
            ]);
        } catch (QueryException $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Không thể tải danh sách nhân viên.',
            ], 500);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {

            return response()->json([
                'success' => false,
                'message' => 'Không thể tải danh sách nhân viên.',
            ], 500);
        }
    }

    /**
     * =========================================================
     * CHI TIẾT CHẤM CÔNG CỦA NHÂN VIÊN
     * =========================================================
     *
     * GET /api/v1/cham-cong
     *
     * ?ma_nv=NV001
     * &thang=8
     * &nam=2026
     * &page=1
     * &per_page=15
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

            $perPage = min(
                100,
                max(
                    1,
                    (int) ($validated['per_page'] ?? 15)
                )
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

                'data' => $paginator,

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
            $rows = DB::select(
                'CALL sp_phong_ban_danh_sach()'
            );

            return response()->json([
                'success' => true,
                'data' => $rows,
            ]);

        } catch (QueryException $exception) {

            return $this->queryError(
                $exception,
                'Không thể tải danh sách phòng ban.'
            );
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
        Request $request,
        int $cham_cong
    ): JsonResponse {
        try {
            $validated = $request->validate([
                'so_gio_lam' => [
                    'required',
                    'numeric',
                    'min:0',
                    'max:24',
                ],

                'vao_muon' => [
                    'required',
                    'boolean',
                ],

                've_som' => [
                    'required',
                    'boolean',
                ],
            ]);

            /*
             * Frontend chỉ gửi:
             *
             * so_gio_lam
             * vao_muon
             * ve_som
             *
             * Nhưng SP cần thêm:
             *
             * ma_nv
             * ngay_lam
             *
             * => lấy record hiện tại trước.
             */
            $attendance = DB::table('cham_cong')
                ->where(
                    'ma_cc',
                    $cham_cong
                )
                ->first();

            if (! $attendance) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy dữ liệu chấm công.',
                ], 404);
            }

            DB::statement(
                '
                CALL sp_cham_cong_cap_nhat(
                    ?, ?, ?, ?, ?, ?
                )
                ',
                [
                    $cham_cong,

                    $attendance->ma_nv,

                    $attendance->ngay_lam,

                    $validated['so_gio_lam'],

                    (int) $validated['vao_muon'],

                    (int) $validated['ve_som'],
                ]
            );

            /*
             * Lấy lại record sau update.
             */
            $updated = DB::table('cham_cong')
                ->where(
                    'ma_cc',
                    $cham_cong
                )
                ->first();

            return response()->json([
                'success' => true,

                'message' => 'Cập nhật chấm công thành công.',

                'data' => $updated,
            ]);

        } catch (QueryException $exception) {

            return $this->queryError(
                $exception,
                'Không thể cập nhật chấm công.'
            );

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
     * '' -> NULL
     */
    private function nullIfEmpty(
        mixed $value
    ): mixed {
        if ($value === null) {
            return null;
        }

        if (
            is_string($value) &&
            trim($value) === ''
        ) {
            return null;
        }

        return is_string($value)
            ? trim($value)
            : $value;
    }

    /**
     * Trả message SIGNAL của MariaDB/MySQL.
     */
    private function queryError(
        QueryException $exception,
        string $fallback
    ): JsonResponse {
        report($exception);

        return response()->json([
            'success' => false,
            'message' => $fallback,
        ], 422);
    }
}
