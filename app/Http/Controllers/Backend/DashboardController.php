<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * API: Lấy dữ liệu tổng quan cho Dashboard
     * 
     * @return JsonResponse
     */
    public function overview(): JsonResponse
    {
        try {
            $data = $this->dashboardService->getOverview();
            
            // Thêm thông tin tổng hợp nhanh
            $data['tong_nhan_vien'] = $this->dashboardService->getTotalEmployees();
            $data['tong_phong_ban'] = $this->dashboardService->getTotalDepartments();

            return response()->json([
                'success' => true,
                'data' => $data,
                'timestamp' => now()->toIso8601String(),
                'message' => 'Lấy dữ liệu thành công'
            ]);
        } catch (\Exception $e) {
            Log::error('[DashboardController] Lỗi lấy dữ liệu overview: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Không thể lấy dữ liệu dashboard',
                'error' => config('app.debug') ? $e->getMessage() : 'Vui lòng thử lại sau',
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
        try {
            $data = $this->dashboardService->getEmployeeCountByEducation();
            
            return response()->json([
                'success' => true,
                'data' => $data,
                'timestamp' => now()->toIso8601String()
            ]);
        } catch (\Exception $e) {
            Log::error('[DashboardController] Lỗi lấy thống kê học vấn: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Không thể lấy thống kê học vấn',
                'error' => config('app.debug') ? $e->getMessage() : null
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
        try {
            $data = $this->dashboardService->getEmployeeCountByDepartment();
            
            return response()->json([
                'success' => true,
                'data' => $data,
                'timestamp' => now()->toIso8601String()
            ]);
        } catch (\Exception $e) {
            Log::error('[DashboardController] Lỗi lấy thống kê phòng ban: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Không thể lấy thống kê phòng ban',
                'error' => config('app.debug') ? $e->getMessage() : null
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
        try {
            $days = (int) $request->input('days', 30);
            $data = $this->dashboardService->getExpiringContracts($days);
            
            return response()->json([
                'success' => true,
                'data' => $data,
                'meta' => [
                    'days' => $days,
                    'total' => count($data)
                ],
                'timestamp' => now()->toIso8601String()
            ]);
        } catch (\Exception $e) {
            Log::error('[DashboardController] Lỗi lấy danh sách hợp đồng: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Không thể lấy danh sách hợp đồng sắp hết hạn',
                'error' => config('app.debug') ? $e->getMessage() : null
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
        try {
            $data = $this->dashboardService->getAttendanceReport();
            
            return response()->json([
                'success' => true,
                'data' => $data,
                'timestamp' => now()->toIso8601String()
            ]);
        } catch (\Exception $e) {
            Log::error('[DashboardController] Lỗi lấy báo cáo chấm công: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Không thể lấy báo cáo chấm công',
                'error' => config('app.debug') ? $e->getMessage() : null
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
        try {
            $data = $this->dashboardService->getSalaryReport();
            
            return response()->json([
                'success' => true,
                'data' => $data,
                'timestamp' => now()->toIso8601String()
            ]);
        } catch (\Exception $e) {
            Log::error('[DashboardController] Lỗi lấy báo cáo lương: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Không thể lấy báo cáo lương',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}