<?php

namespace App\Http\Controllers\Backend;

use App\Contracts\VaiTroServiceContract;
use App\Exceptions\VaiTroDomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVaiTroRequest;
use App\Http\Requests\UpdateVaiTroRequest;
use App\Http\Requests\ListVaiTroRequest;
use App\Support\JsonPaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

final class VaiTroController extends Controller
{
    public function __construct(private VaiTroServiceContract $roles) {}
    public function index(ListVaiTroRequest $request): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => JsonPaginator::from($this->roles->paginate($request->filters())),
            ]);
        } catch (Throwable $exception) {
            report($exception);
            return response()->json([
                'success' => false,
                'message' => 'Không thể tải danh sách vai trò.',
            ], 500);
        }
    }

    public function search(ListVaiTroRequest $request): JsonResponse { return $this->index($request); }
    public function show(int $ma_vt): JsonResponse { try { return response()->json(['success' => true, 'data' => $this->roles->findOrFail($ma_vt)]); } catch (VaiTroDomainException) { return response()->json(['success' => false, 'message' => 'Không tìm thấy vai trò.'], 404); } }
    public function store(StoreVaiTroRequest $request): JsonResponse { try { $id = $this->roles->create($request->validated()); return response()->json(['success' => true, 'message' => 'Tạo vai trò thành công.', 'data' => $this->roles->findOrFail($id)], 201); } catch (VaiTroDomainException $e) { return response()->json(['success' => false, 'message' => $e->getMessage()], 422); } catch (Throwable) { return response()->json(['success' => false, 'message' => 'Không thể tạo vai trò lúc này.'], 500); } }
    public function update(UpdateVaiTroRequest $request, int $ma_vt): JsonResponse { try { $this->roles->update($ma_vt, $request->validated()); return response()->json(['success' => true, 'message' => 'Cập nhật vai trò thành công.', 'data' => $this->roles->findOrFail($ma_vt)]); } catch (VaiTroDomainException $e) { return response()->json(['success' => false, 'message' => $e->getMessage()], $e->errorCode === 'ROLE_NOT_FOUND' ? 404 : 422); } catch (Throwable) { return response()->json(['success' => false, 'message' => 'Không thể cập nhật vai trò lúc này.'], 500); } }
    public function destroy(int $ma_vt): JsonResponse { try { $this->roles->delete($ma_vt); return response()->json(['success' => true, 'message' => 'Xóa vai trò thành công.']); } catch (VaiTroDomainException $e) { return response()->json(['success' => false, 'message' => $e->getMessage()], $e->errorCode === 'ROLE_NOT_FOUND' ? 404 : 409); } catch (Throwable) { return response()->json(['success' => false, 'message' => 'Không thể xóa vai trò lúc này.'], 500); } }
}
