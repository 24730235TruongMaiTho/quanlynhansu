<?php

namespace App\Http\Controllers\Backend;

use App\Contracts\ChucVuServiceContract;
use App\Exceptions\ChucVuDomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChucVuRequest;
use App\Http\Requests\UpdateChucVuRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Throwable;

final class ChucVuController extends Controller
{
    public function __construct(private ChucVuServiceContract $positions) {}

    public function index(Request $request): View
    {
        $positionError = null;
        $positions = new LengthAwarePaginator([], 0, 10, 1);
        $search = trim((string) $request->query('ten_cv', ''));
        $pageSize = $this->pageSize($request->query('per_page'));

        try {
            $allPositions = $this->positions->all();
            if ($search !== '') {
                $allPositions = array_values(array_filter(
                    $allPositions,
                    static fn (object $position): bool => mb_stripos($position->ten_cv, $search) !== false,
                ));
            }

            $totalPositions = count($allPositions);
            $lastPage = max(1, (int) ceil($totalPositions / $pageSize));
            $currentPage = min(max(1, $request->integer('page', 1)), $lastPage);
            $positions = new LengthAwarePaginator(
                array_slice($allPositions, ($currentPage - 1) * $pageSize, $pageSize),
                $totalPositions,
                $pageSize,
                $currentPage,
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ],
            );
        } catch (Throwable) {
            $positionError = 'Không thể tải danh sách chức vụ lúc này. Vui lòng thử lại sau.';
        }

        return view('backend.chucvu.index', compact('positions', 'positionError', 'search', 'pageSize'));
    }

    public function create(): View
    {
        return view('backend.chucvu.create');
    }

    public function store(StoreChucVuRequest $request): RedirectResponse|JsonResponse
    {
        try {
            $this->positions->create(
                (string) $request->validated('ten_cv'),
                (string) $request->validated('he_so_phu_cap'),
            );
        } catch (ChucVuDomainException $exception) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $exception->getMessage(),
                    'errors' => [$exception->field ?? 'chuc_vu' => [$exception->getMessage()]],
                ], 422);
            }

            return back()->withInput()->withErrors([
                $exception->field ?? 'chuc_vu' => $exception->getMessage(),
            ]);
        } catch (Throwable) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Không thể tạo chức vụ lúc này. Vui lòng thử lại sau.',
                ], 500);
            }

            return back()->withInput()->withErrors([
                'chuc_vu' => 'Không thể tạo chức vụ lúc này. Vui lòng thử lại sau.',
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Đã thêm chức vụ.'], 201);
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

    private function pageSize(mixed $value): int
    {
        $pageSize = filter_var($value, FILTER_VALIDATE_INT);

        return in_array($pageSize, [5, 10, 20], true) ? $pageSize : 10;
    }
}
