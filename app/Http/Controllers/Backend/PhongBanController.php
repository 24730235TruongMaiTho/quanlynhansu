<?php
namespace App\Http\Controllers\Backend;

use App\Models\PhongBan;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB; //Thư viện để làm việc với database

class PhongBanController extends Controller
{
    // Danh sách phòng ban
    public function index()
    {
        // Gọi store procedure để lấy danh sách
        $danh_sach_phong_ban = DB::select('CALL sp_phong_ban_danh_sach()');

        return view('backend.phongban.index', compact('phongban'));

    }

    // Thêm phòng ban
    public function store(Request $request)
    {
        // Xác thực dữ liệu nhập vào
        $request->validate([
            'ten_pb' => 'required'
        ]);

        DB::statement('CALL sp_phong_ban_them(?)', [$request->ten_pb]);

        return redirect()->route('backend.phongban.index')->with('success', 'Thêm phòng ban thành công');
    }

    // Chi tiết phòng ban
    public function edit($id)
    {
        $phong_ban = DB::select('CALL sp_phong_ban_chi_tiet(?)', [$id]);

        return view('backend.phongban.index', [
            'phongban' => $phong_ban[0]
        ]);
    }

    // Cập nhật phòng ban
    public function update(Request $request, $id)
    {
        DB::statement('CALL sp_phong_ban_sua(?)', 
        [
            $id,
            $request->ten_pb
        ]);

        return redirect()->route('backend.phongban.index');
    }

    // Xóa phòng ban
    public function detroy($id)
    {
        DB::statement('CALL sp_phong_ban_xoa(?)', [$id]);

        return redirect()->route('backend.phongban.index');
    }
}

?>