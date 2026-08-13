<?php

namespace App\Http\Controllers\Backend;

use App\Contracts\NhanVienServiceContract;
use App\Exceptions\NhanVienDomainException;
use App\Http\Requests\ListNhanVienRequest;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class NhanVienController extends Controller
{
    public function __construct(private NhanVienServiceContract $employees)
    {
    }

    public function index(ListNhanVienRequest $request): View
    {
        $filters = $request->filters();
        $employeeError = null;

        try {
            $employees = $this->employees->paginate($filters);
            $lookups = $this->employees->lookups();
        } catch (NhanVienDomainException) {
            $employees = new LengthAwarePaginator(
                collect(),
                0,
                $filters['so_dong'],
                $filters['page'],
                ['pageName' => 'page'],
            );
            $lookups = [
                'phong_ban' => [],
                'chuc_vu' => [],
                'trang_thai' => [],
            ];
            $employeeError = 'Không thể tải danh sách nhân viên lúc này. Vui lòng thử lại sau.';
        }

        $employees
            ->withPath(route('backend.nhanvien.index'))
            ->appends($filters);

        return view('backend.nhanvien.index', [
            'employees' => $employees,
            'lookups' => $lookups,
            'filters' => $filters,
            'employeeError' => $employeeError,
        ]);
    }
}
