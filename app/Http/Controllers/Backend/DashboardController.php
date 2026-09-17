<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\NhanVien;
use App\Services\DashboardService;
use App\Services\PersonalDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Controller xử lý các API cho Dashboard
 *
 * @package App\Http\Controllers\Backend
 */
class DashboardController extends Controller
{
    /**
     * @var DashboardService
     */
    protected DashboardService $dashboardService;

    /**
     * Constructor
     *
     * @param DashboardService $dashboardService
     */
    public function __construct(
        DashboardService $dashboardService,
        private PersonalDashboardService $personalDashboardService,
    )
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * API: Lấy dữ liệu tổng quan cho Dashboard, bao gồm pending_leave_count
     *
     * @return JsonResponse
     */
    public function overview(): JsonResponse
    {
        $actor = $this->actor();

        try {
            $data = $this->dashboardService->getOverview($actor);

            return response()->json([
                'success' => true,
                'data' => $data,
                'timestamp' => now()->toIso8601String(),
                'message' => 'Lấy dữ liệu thành công'
            ]);
        } catch (Throwable $exception) {
            Log::warning('dashboard_controller_failed', [
                'endpoint' => 'overview',
                'exception_class' => $exception::class,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Không thể lấy dữ liệu dashboard',
                'error' => 'Vui lòng thử lại sau',
                'timestamp' => now()->toIso8601String()
            ], 500);
        }
    }

    /**
     * API: Lấy thống kê nhân viên theo học vấn
     *
     * @return JsonResponse
     */
    public function educationStats(): JsonResponse
    {
        $actor = $this->actor();

        try {
            $data = $this->dashboardService->getEmployeeCountByEducation($actor);

            return response()->json([
                'success' => true,
                'data' => $data,
                'timestamp' => now()->toIso8601String()
            ]);
        } catch (Throwable $exception) {
            Log::warning('dashboard_controller_failed', [
                'endpoint' => 'education',
                'exception_class' => $exception::class,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Không thể lấy thống kê học vấn',
                'error' => null
            ], 500);
        }
    }

    /**
     * API: Lấy thống kê nhân viên theo phòng ban
     *
     * @return JsonResponse
     */
    public function departmentStats(): JsonResponse
    {
        $actor = $this->actor();

        try {
            $data = $this->dashboardService->getEmployeeCountByDepartment($actor);

            return response()->json([
                'success' => true,
                'data' => $data,
                'timestamp' => now()->toIso8601String()
            ]);
        } catch (Throwable $exception) {
            Log::warning('dashboard_controller_failed', [
                'endpoint' => 'department',
                'exception_class' => $exception::class,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Không thể lấy thống kê phòng ban',
                'error' => null
            ], 500);
        }
    }

    /**
     * API: Lấy danh sách hợp đồng sắp hết hạn
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function expiringContracts(Request $request): JsonResponse
    {
        $actor = $this->actor();

        try {
            $days = max(0, min((int) $request->input('days', 30), 365));
            $data = $this->dashboardService->getExpiringContracts($days, $actor);

            return response()->json([
                'success' => true,
                'data' => $data,
                'meta' => [
                    'days' => $days,
                    'total' => count($data)
                ],
                'timestamp' => now()->toIso8601String()
            ]);
        } catch (Throwable $exception) {
            Log::warning('dashboard_controller_failed', [
                'endpoint' => 'expiring_contracts',
                'exception_class' => $exception::class,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Không thể lấy danh sách hợp đồng sắp hết hạn',
                'error' => null
            ], 500);
        }
    }

    /**
     * API: Lấy báo cáo chấm công
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function attendanceReport(Request $request): JsonResponse
    {
        $actor = $this->actor();

        try {
            $data = $this->dashboardService->getAttendanceReport($actor);

            return response()->json([
                'success' => true,
                'data' => $data,
                'timestamp' => now()->toIso8601String()
            ]);
        } catch (Throwable $exception) {
            Log::warning('dashboard_controller_failed', [
                'endpoint' => 'attendance',
                'exception_class' => $exception::class,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Không thể lấy báo cáo chấm công',
                'error' => null
            ], 500);
        }
    }

    /**
     * API: Lấy báo cáo lương
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function salaryReport(Request $request): JsonResponse
    {
        $actor = $this->actor();

        try {
            $data = $this->dashboardService->getSalaryReport($actor);

            return response()->json([
                'success' => true,
                'data' => $data,
                'timestamp' => now()->toIso8601String()
            ]);
        } catch (Throwable $exception) {
            Log::warning('dashboard_controller_failed', [
                'endpoint' => 'salary',
                'exception_class' => $exception::class,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Không thể lấy báo cáo lương',
                'error' => null
            ], 500);
        }
    }

    /** API: tổng hợp dữ liệu tự phục vụ chính chủ của actor. */
    public function personal(): JsonResponse
    {
        $actor = $this->actor();

        try {
            return response()->json([
                'success' => true,
                'data' => $this->personalDashboardService->getOverview($actor),
                'timestamp' => now()->toIso8601String(),
            ]);
        } catch (HttpExceptionInterface $exception) {
            Log::warning('dashboard_controller_rejected_actor', [
                'endpoint' => 'personal',
                'exception_class' => $exception::class,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Tài khoản không hợp lệ.',
                'error' => null,
                'timestamp' => now()->toIso8601String(),
            ], $exception->getStatusCode());
        } catch (Throwable $exception) {
            Log::warning('dashboard_controller_failed', [
                'endpoint' => 'personal',
                'exception_class' => $exception::class,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Không thể tải dữ liệu cá nhân lúc này.',
                'error' => null,
                'timestamp' => now()->toIso8601String(),
            ], 500);
        }
    }

    private function actor(): NhanVien
    {
        $actor = auth()->user();
        abort_unless($actor instanceof NhanVien, 403, 'Tài khoản không hợp lệ.');

        return $actor;
    }
}
