<?php
namespace App\Http\Controllers\Backend;

use App\Models\NhanVien;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB; //Thư viện để làm việc với database

class NhanVienController extends Controller
{
    // Danh sách nhân viên
    public function index()
    {
        return view("backend.nhanvien.index");
        #return view("backend.nhanvien.index", compact('nhanvien'));
    }

    public function create()
    {
        return view('backend.nhanvien.create');
    }

    // Thêm nhân viên
    public function store(Request $request)
    {
        return redirect()->route('backend.nhanvien.index')->with('success', 'Thêm nhân viên thành công');
    }
}
?>