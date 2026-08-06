<?php
namespace App\Http\Controllers\Backend;

use Illuminate\Routing\Controller;

class BangDieuKhienController extends Controller
{
    public function index()
    {
        return view('backend.bangdieukhien.index');
    }
}
?>