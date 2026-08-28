<?php
namespace App\Http\Controllers\Backend;

use App\Contracts\PhongBanServiceContract;
use App\Exceptions\PhongBanDomainException;
use App\Http\Requests\StorePhongBanRequest;
use App\Http\Requests\ListPhongBanRequest;
use App\Http\Requests\UpdatePhongBanRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Throwable;

class PhongBanController extends Controller
{
    public function __construct(private PhongBanServiceContract $departments) {}

    public function index(ListPhongBanRequest $request): View
    {
        $filters = $request->filters();
        $departmentError = null;
        $departments = $this->emptyPaginator($filters);

        try {
            $departments = $this->departments->paginate($filters);
        } catch (Throwable) {
            $departmentError = 'Không thể tải danh sách phòng ban lúc này. Vui lòng thử lại sau.';
        }

        $departments
            ->withPath(route('backend.phongban.index'))
            ->appends($filters);

        return view('backend.phongban.index', compact('departments', 'departmentError', 'filters'));
    }

    public function create(): View
    {
        return view('backend.phongban.create');
    }

    public function store(StorePhongBanRequest $request): RedirectResponse
    {
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
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($id === false || $id === null) {
            abort(404);
        }

        return $id;
    }

    /** @param array{ten_pb: ?string, page: int, so_dong: int} $filters */
    private function emptyPaginator(array $filters): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            collect(),
            0,
            $filters['so_dong'],
            $filters['page'],
            ['pageName' => 'page'],
        );
    }
}
