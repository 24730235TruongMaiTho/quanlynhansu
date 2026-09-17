<?php

namespace App\Http\Controllers\Backend;

use App\Contracts\HopDongServiceContract;
use App\Exceptions\HopDongDomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListHopDongRequest;
use App\Http\Requests\StoreHopDongRequest;
use App\Http\Requests\UpdateHopDongRequest;
use App\Support\CurrentEmployee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

final class HopDongController extends Controller
{
    public function __construct(
        private HopDongServiceContract $contracts,
        private CurrentEmployee $currentEmployee,
    ) {}

    public function own(Request $request): View
    {
        $contracts = $this->contracts->paginateForEmployee(
            $this->currentEmployee->id($request->user()),
            20,
        );

        return view('backend.selfservice.hopdong', compact('contracts'));
    }

    public function index(ListHopDongRequest $request): View
    {
        $contracts = $this->contracts->paginate($request->validated());
        ['types' => $types] = $this->contracts->formOptions();
        return view('backend.hopdong.index', compact('contracts', 'types'));
    }

    public function create(Request $request): View
    {
        if ($request->header('X-Create-Modal') === '1'
            || ($request->ajax() && $request->header('X-Form-Modal') === 'create')) {
            return view('backend.hopdong.partials.create-modal-content', $this->contracts->formOptions());
        }

        return view('backend.hopdong.form', $this->contracts->formOptions());
    }

    public function store(StoreHopDongRequest $request): JsonResponse|RedirectResponse
    {
        try {
            $this->contracts->create($request->validated());
        } catch (HopDongDomainException $e) {
            if ($request->expectsJson()) {
                if ($e->errorCode === 'HD_PERSIST_FAILED') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Không thể tạo hợp đồng lúc này.',
                    ], 500);
                }

                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errors' => [$e->field ?? 'hop_dong' => [$e->getMessage()]],
                ], 422);
            }

            return back()->withInput()->withErrors([$e->field ?? 'hop_dong' => $e->getMessage()]);
        } catch (Throwable) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể tạo hợp đồng lúc này.',
                ], 500);
            }

            return back()->withInput()->withErrors(['hop_dong' => 'Không thể tạo hợp đồng lúc này.']);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Đã thêm hợp đồng.',
            ], 201);
        }

        return redirect()->route('backend.hopdong.index')->with('success', 'Đã thêm hợp đồng.');
    }

    public function edit(Request $request, int $ma_hd): View
    {
        try { $contract = $this->contracts->findOrFail($ma_hd); }
        catch (HopDongDomainException) { abort(404); }
        $viewData = ['contract' => $contract] + $this->contracts->formOptions();

        if ($request->header('X-Edit-Modal') === '1'
            || ($request->ajax() && $request->header('X-Form-Modal') === 'edit')) {
            return view('backend.hopdong.partials.edit-modal-content', $viewData);
        }

        return view('backend.hopdong.form', $viewData);
    }

    public function update(UpdateHopDongRequest $request, int $ma_hd): JsonResponse|RedirectResponse
    {
        try {
            $this->contracts->update($ma_hd, $request->validated());
        } catch (HopDongDomainException $e) {
            if ($request->expectsJson()) {
                if ($e->errorCode === 'HD_NOT_FOUND') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Không tìm thấy hợp đồng.',
                    ], 404);
                }

                if ($e->errorCode === 'HD_PERSIST_FAILED') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Không thể cập nhật hợp đồng lúc này.',
                    ], 500);
                }

                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errors' => [$e->field ?? 'hop_dong' => [$e->getMessage()]],
                ], 422);
            }

            if ($e->errorCode === 'HD_NOT_FOUND') abort(404);
            return back()->withInput()->withErrors([$e->field ?? 'hop_dong' => $e->getMessage()]);
        } catch (Throwable) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể cập nhật hợp đồng lúc này.',
                ], 500);
            }

            return back()->withInput()->withErrors(['hop_dong' => 'Không thể cập nhật hợp đồng lúc này.']);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Đã cập nhật hợp đồng.',
            ]);
        }

        return redirect()->route('backend.hopdong.index')->with('success', 'Đã cập nhật hợp đồng.');
    }

    public function destroy(int $ma_hd): RedirectResponse
    {
        try { $this->contracts->delete($ma_hd); }
        catch (HopDongDomainException $e) { if ($e->errorCode === 'HD_NOT_FOUND') abort(404); return back()->withErrors(['hop_dong' => $e->getMessage()]); }
        catch (Throwable) { return back()->withErrors(['hop_dong' => 'Không thể xóa hợp đồng lúc này.']); }
        return redirect()->route('backend.hopdong.index')->with('success', 'Đã xóa hợp đồng.');
    }
}
