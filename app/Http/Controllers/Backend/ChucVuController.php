<?php

namespace App\Http\Controllers\Backend;

use App\Contracts\ChucVuServiceContract;
use App\Exceptions\ChucVuDomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChucVuRequest;
use App\Http\Requests\ListChucVuRequest;
use App\Http\Requests\UpdateChucVuRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Throwable;

final class ChucVuController extends Controller
{
    public function __construct(private ChucVuServiceContract $positions) {}

    public function index(ListChucVuRequest $request): View
    {
        $filters = $request->filters();
        $positionError = null;
        $positions = $this->emptyPaginator($filters);

        try {
            $positions = $this->positions->paginate($filters);
        } catch (Throwable) {
            $positionError = 'Không thể tải danh sách chức vụ lúc này. Vui lòng thử lại sau.';
        }

        $positions
            ->withPath(route('backend.chucvu.index'))
            ->appends($filters);

        return view('backend.chucvu.index', compact('positions', 'positionError', 'filters'));
    }

    public function create(): View
    {
        return view('backend.chucvu.create');
    }

    public function store(StoreChucVuRequest $request): RedirectResponse
    {
        try {
            $this->positions->create(
                (string) $request->validated('ten_cv'),
                (string) $request->validated('he_so_phu_cap'),
            );
        } catch (ChucVuDomainException $exception) {
            return back()->withInput()->withErrors([
                $exception->field ?? 'chuc_vu' => $exception->getMessage(),
            ]);
        } catch (Throwable) {
            return back()->withInput()->withErrors([
                'chuc_vu' => 'Không thể tạo chức vụ lúc này. Vui lòng thử lại sau.',
            ]);
        }

        return redirect()->route('backend.chucvu.index')->with('success', 'Đã thêm chức vụ.');
    }

    public function edit(string $ma_cv): View
    {
        try {
            $position = $this->positions->findOrFail($this->positionId($ma_cv));
        } catch (ChucVuDomainException $exception) {
            if ($exception->domainCode === 'CV_NOT_FOUND') {
                abort(404);
            }

            abort(503);
        } catch (Throwable) {
            abort(503);
        }

        return view('backend.chucvu.edit', compact('position'));
    }

    public function update(UpdateChucVuRequest $request, string $ma_cv): RedirectResponse
    {
        $positionId = $this->positionId($ma_cv);

        try {
            $this->positions->update(
                $positionId,
                (string) $request->validated('ten_cv'),
                (string) $request->validated('he_so_phu_cap'),
            );
        } catch (ChucVuDomainException $exception) {
            if ($exception->domainCode === 'CV_NOT_FOUND') {
                abort(404);
            }

            return back()->withInput()->withErrors([
                $exception->field ?? 'chuc_vu' => $exception->getMessage(),
            ]);
        } catch (Throwable) {
            return back()->withInput()->withErrors([
                'chuc_vu' => 'Không thể cập nhật chức vụ lúc này. Vui lòng thử lại sau.',
            ]);
        }

        return redirect()->route('backend.chucvu.index')->with('success', 'Đã cập nhật chức vụ.');
    }

    public function destroy(string $ma_cv): RedirectResponse
    {
        try {
            $this->positions->delete($this->positionId($ma_cv));
        } catch (ChucVuDomainException $exception) {
            if ($exception->domainCode === 'CV_NOT_FOUND') {
                abort(404);
            }

            return back()->withErrors([
                $exception->field ?? 'chuc_vu' => $exception->getMessage(),
            ]);
        } catch (Throwable) {
            return back()->withErrors([
                'chuc_vu' => 'Không thể xóa chức vụ lúc này. Vui lòng thử lại sau.',
            ]);
        }

        return redirect()->route('backend.chucvu.index')->with('success', 'Đã xóa chức vụ.');
    }

    private function positionId(string $value): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($id === false || $id === null) {
            abort(404);
        }

        return $id;
    }

    /** @param array{ten_cv: ?string, page: int, so_dong: int} $filters */
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
