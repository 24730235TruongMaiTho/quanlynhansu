<?php

namespace App\Http\Controllers\Backend;

use App\Contracts\PhanQuyenServiceContract;
use App\Contracts\VaiTroServiceContract;
use App\Exceptions\PhanQuyenDomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssignVaiTroRequest;
use App\Http\Requests\SyncVaiTroQuyenRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

final class PhanQuyenController extends Controller
{
    public function __construct(private PhanQuyenServiceContract $authorization, private VaiTroServiceContract $roles) {}
    public function editRole(int $ma_vt): View { return view('backend.vaitro.permissions', ['role' => $this->roles->findOrFail($ma_vt), 'permissions' => $this->authorization->permissionsByModule(), 'selected' => $this->authorization->permissionIdsForRole($ma_vt)]); }
    public function syncRole(SyncVaiTroQuyenRequest $request, int $ma_vt): RedirectResponse { try { $this->authorization->syncRolePermissions($ma_vt, $request->validated('ma_quyen')); } catch (PhanQuyenDomainException $e) { return back()->withErrors(['phan_quyen' => $e->getMessage()]); } catch (Throwable) { return back()->withErrors(['phan_quyen' => 'Không thể cập nhật quyền lúc này.']); } return back()->with('success', 'Đã cập nhật quyền cho vai trò.'); }
    public function accounts(Request $request): View { return view('backend.taikhoan.index', ['accounts' => $this->authorization->accounts((string) $request->query('keyword', '')), 'roles' => $this->roles->all()]); }
    public function assignRole(AssignVaiTroRequest $request, string $ma_nv): RedirectResponse { try { $this->authorization->assignRole($ma_nv, (int) $request->validated('ma_vt'), (string) auth()->id()); } catch (PhanQuyenDomainException $e) { return back()->withErrors(['phan_quyen' => $e->getMessage()]); } catch (Throwable) { return back()->withErrors(['phan_quyen' => 'Không thể gán vai trò lúc này.']); } return back()->with('success', 'Đã cập nhật vai trò tài khoản.'); }
}
