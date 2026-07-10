namespace app/Http/Controllers/Backend;

use app/Models/NhanVien;

class NhanVienController extends Controller
{
    public function index()
    {
        return view("Backend.NhanVien.Index");
    }
}