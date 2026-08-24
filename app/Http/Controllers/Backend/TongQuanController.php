<?php
namespace App\Http\Controllers\Backend;

use Illuminate\Routing\Controller;

class TongQuanController extends Controller
{
    public function index()
    {
        return view('backend.tongquan.index');
    }
}
?>