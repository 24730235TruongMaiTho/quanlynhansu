<?php

namespace App\Http\Controllers\Backend;

use App\Models\VaiTro;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class VaiTroController extends Controller
{
	public function index(Request $request): JsonResponse
	{
		$keyword = trim((string) $request->input('ten_vt', ''));

		$roles = VaiTro::query()
			->when($keyword !== '', function ($query) use ($keyword): void {
				$query->where(function ($query) use ($keyword): void {
						$query->where('ten_vt', 'like', "%{$keyword}%");
				});
			})
			->orderBy('ma_vt')
			->get();

		return response()->json([
			'success' => true,
			'data' => $roles,
		]);
	}

	public function search(Request $request): JsonResponse
	{
		return $this->index($request);
	}

	public function show(int $ma_vt): JsonResponse
	{
		$role = VaiTro::find($ma_vt);

		if ($role === null) {
			return response()->json([
				'success' => false,
				'message' => 'Không tìm thấy vai trò.',
			], 404);
		}

		return response()->json([
			'success' => true,
			'data' => $role,
		]);
	}

	public function store(Request $request): JsonResponse
	{
		$data = $request->validate([
			'ten_vt' => ['required', 'string', 'max:100'],
			'mo_ta' => ['nullable', 'string', 'max:255'],
		]);

		$role = VaiTro::create($data);

		return response()->json([
			'success' => true,
			'message' => 'Tạo vai trò thành công.',
			'data' => $role,
		], 201);
	}

	public function update(Request $request, int $ma_vt): JsonResponse
	{
		$role = VaiTro::find($ma_vt);

		if ($role === null) {
			return response()->json([
				'success' => false,
				'message' => 'Không tìm thấy vai trò.',
			], 404);
		}

		$data = $request->validate([
			'ten_vt' => ['sometimes', 'required', 'string', 'max:100'],
			'mo_ta' => ['sometimes', 'nullable', 'string', 'max:255'],
		]);

		$role->update($data);

		return response()->json([
			'success' => true,
			'message' => 'Cập nhật vai trò thành công.',
			'data' => $role->fresh(),
		]);
	}

	public function destroy(int $ma_vt): JsonResponse
	{
		$role = VaiTro::find($ma_vt);

		if ($role === null) {
			return response()->json([
				'success' => false,
				'message' => 'Không tìm thấy vai trò.',
			], 404);
		}

		try {
			$role->delete();
		} catch (QueryException) {
			return response()->json([
				'success' => false,
				'message' => 'Không thể xóa vai trò đang được sử dụng.',
			], 409);
		}

		return response()->json([
			'success' => true,
			'message' => 'Xóa vai trò thành công.',
		]);
	}
}
