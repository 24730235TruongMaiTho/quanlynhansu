<?php

namespace App\Http\Controllers\Backend;

use App\Contracts\HopDongServiceContract;
use App\Exceptions\HopDongDomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListHopDongRequest;
use App\Http\Requests\StoreHopDongRequest;
use App\Http\Requests\UpdateHopDongRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Throwable;

final class HopDongController extends Controller
{
    public function __construct(private HopDongServiceContract $contracts) {}

    public function index(ListHopDongRequest $request): View
    {
        $contracts = $this->contracts->paginate($request->validated());
        ['types' => $types] = $this->contracts->formOptions();
        return view('backend.hopdong.index', compact('contracts', 'types'));
    }

    public function create(): View
    {
        return view('backend.hopdong.form', $this->contracts->formOptions());
    }

    public function store(StoreHopDongRequest $request): RedirectResponse
    {
        try { $this->contracts->create($request->validated()); }
        catch (HopDongDomainException $e) { return back()->withInput()->withErrors([$e->field ?? 'hop_dong' => $e->getMessage()]); }
        catch (Throwable) { return back()->withInput()->withErrors(['hop_dong' => 'Không thể tạo hợp đồng lúc này.']); }
        return redirect()->route('backend.hopdong.index')->with('success', 'Đã thêm hợp đồng.');
    }

    public function edit(int $ma_hd): View
    {
        try { $contract = $this->contracts->findOrFail($ma_hd); }
        catch (HopDongDomainException) { abort(404); }
        return view('backend.hopdong.form', ['contract' => $contract] + $this->contracts->formOptions());
    }

    public function update(UpdateHopDongRequest $request, int $ma_hd): RedirectResponse
    {
        try { $this->contracts->update($ma_hd, $request->validated()); }
        catch (HopDongDomainException $e) { if ($e->errorCode === 'HD_NOT_FOUND') abort(404); return back()->withInput()->withErrors([$e->field ?? 'hop_dong' => $e->getMessage()]); }
        catch (Throwable) { return back()->withInput()->withErrors(['hop_dong' => 'Không thể cập nhật hợp đồng lúc này.']); }
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
