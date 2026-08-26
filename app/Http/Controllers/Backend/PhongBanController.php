<?php
namespace App\Http\Controllers\Backend;

use App\Contracts\PhongBanServiceContract;
use App\Exceptions\PhongBanDomainException;
use App\Http\Requests\StorePhongBanRequest;
use App\Http\Requests\UpdatePhongBanRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Throwable;

class PhongBanController extends Controller
{
    // Controller chỉ điều phối request/response; nghiệp vụ dữ liệu nằm ở service.
    public function __construct(private PhongBanServiceContract $departments) {}

    public function index(Request $request): View
    {
        $departmentError = null;
        $departments = new LengthAwarePaginator([], 0, 10, 1);
        $search = trim((string) $request->query('ten_pb', ''));
        $pageSize = $this->pageSize($request->query('per_page'));

        try {
            // Không để lỗi hệ thống làm lộ chi tiết database ra giao diện.
            $allDepartments = $this->departments->all();
            if ($search !== '') {
                $allDepartments = array_values(array_filter(
                    $allDepartments,
                    static fn (object $department): bool => mb_stripos($department->ten_pb, $search) !== false,
                ));
            }

            $totalDepartments = count($allDepartments);
            $lastPage = max(1, (int) ceil($totalDepartments / $pageSize));
            $currentPage = min(max(1, $request->integer('page', 1)), $lastPage);
            $departments = new LengthAwarePaginator(
                array_slice($allDepartments, ($currentPage - 1) * $pageSize, $pageSize),
                $totalDepartments,
                $pageSize,
                $currentPage,
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ],
            );
        } catch (Throwable) {
            $departmentError = 'Không thể tải danh sách phòng ban lúc này. Vui lòng thử lại sau.';
        }

        return view('backend.phongban.index', compact('departments', 'departmentError', 'search', 'pageSize'));
    }

    public function create(): View
    {
        return view('backend.phongban.create');
    }

    public function store(StorePhongBanRequest $request): RedirectResponse
    {
        // Request đã xác thực dữ liệu trước khi gọi service tạo phòng ban.
        try {
            $this->departments->create($request->validated('ten_pb'));
        } catch (PhongBanDomainException $exception) {
            return back()->withInput()->withErrors([
                $exception->field ?? 'phong_ban' => $exception->getMessage(),
            ]);
        } catch (Throwable) {
            return back()->withInput()->withErrors([
                'phong_ban' => 'Không thể tạo phòng ban lúc này. Vui lòng thử lại sau.',
            ]);
        }

        return redirect()->route('backend.phongban.index')->with('success', 'Đã thêm phòng ban.');
    }

    public function edit(string $ma_pb): View
    {
        $departmentId = $this->departmentId($ma_pb);

        try {
            // Route nhận chuỗi, nên cần chuẩn hóa và kiểm tra ID trước khi truy vấn.
            $department = $this->departments->findOrFail($departmentId);
        } catch (PhongBanDomainException $exception) {
            if ($exception->domainCode === 'PB_NOT_FOUND') {
                abort(404);
            }

            abort(503);
        } catch (Throwable) {
            abort(503);
        }

        return view('backend.phongban.edit', compact('department'));
    }

    public function update(UpdatePhongBanRequest $request, string $ma_pb): RedirectResponse
    {
        $departmentId = $this->departmentId($ma_pb);

        try {
            // Lỗi nghiệp vụ được trả lại form; lỗi không xác định chỉ trả thông báo an toàn.
            $this->departments->update($departmentId, $request->validated('ten_pb'));
        } catch (PhongBanDomainException $exception) {
            if ($exception->domainCode === 'PB_NOT_FOUND') {
                abort(404);
            }

            return back()->withInput()->withErrors([
                $exception->field ?? 'phong_ban' => $exception->getMessage(),
            ]);
        } catch (Throwable) {
            return back()->withInput()->withErrors([
                'phong_ban' => 'Không thể cập nhật phòng ban lúc này. Vui lòng thử lại sau.',
            ]);
        }

        return redirect()->route('backend.phongban.index')->with('success', 'Đã cập nhật phòng ban.');
    }

    public function destroy(string $ma_pb): RedirectResponse
    {
        $departmentId = $this->departmentId($ma_pb);

        try {
            // Repository chịu trách nhiệm kiểm tra phòng ban đang được nhân viên sử dụng.
            $this->departments->delete($departmentId);
        } catch (PhongBanDomainException $exception) {
            if ($exception->domainCode === 'PB_NOT_FOUND') {
                abort(404);
            }

            return back()->withErrors([
                $exception->field ?? 'phong_ban' => $exception->getMessage(),
            ]);
        } catch (Throwable) {
            return back()->withErrors([
                'phong_ban' => 'Không thể xóa phòng ban lúc này. Vui lòng thử lại sau.',
            ]);
        }

        return redirect()->route('backend.phongban.index')->with('success', 'Đã xóa phòng ban.');
    }

    private function departmentId(string $value): int
    {
        // Chỉ chấp nhận số nguyên dương; giá trị khác được xem như tài nguyên không tồn tại.
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($id === false || $id === null) {
            abort(404);
        }

        return $id;
    }

    private function pageSize(mixed $value): int
    {
        $pageSize = filter_var($value, FILTER_VALIDATE_INT);

        return in_array($pageSize, [5, 10, 20], true) ? $pageSize : 10;
    }
}
