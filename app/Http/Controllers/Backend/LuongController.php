<?php

namespace App\Http\Controllers\Backend;

use App\Http\Requests\StoreLuongRequest;
use App\Http\Requests\UpdateLuongRequest;
use App\Services\LuongService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class LuongController extends Controller
{
    protected $service;

    public function __construct(LuongService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $filters = $request->only(['ma_nv', 'ky_luong', 'ma_pb', 'ma_cv']);
        $result = $this->service->getAll($filters);

        if (!$result['success']) {
            return response()->json($result, 500);
        }

        return response()->json($result);
    }

    public function show($id)
    {
        $result = $this->service->getById($id);

        if (!$result['success']) {
            return response()->json($result, 404);
        }

        return response()->json($result);
    }

    public function store(StoreLuongRequest $request)
    {
        $result = $this->service->create($request->validated());

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result, 201);
    }

    public function update(UpdateLuongRequest $request, $id)
    {
        $result = $this->service->update($id, $request->validated());

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result);
    }

    public function destroy($id)
    {
        $result = $this->service->delete($id);

        if (!$result['success']) {
            return response()->json($result, 404);
        }

        return response()->json($result);
    }

    public function export(Request $request)
    {
        $validated = $request->validate([
            'ky_luong' => [
                'required',
                'string',
            ],

            'tu_khoa' => [
                'nullable',
                'string',
            ],

            'ma_pb' => [
                'nullable',
                'integer',
            ],

            'ma_cv' => [
                'nullable',
                'integer',
            ],
        ]);

        $result = $this->service->exportByKyLuong(
            $validated['ky_luong'],
            [
                'tu_khoa' =>
                    $validated['tu_khoa']
                    ?? null,

                'ma_pb' =>
                    $validated['ma_pb']
                    ?? null,

                'ma_cv' =>
                    $validated['ma_cv']
                    ?? null,
            ]
        );

        if (! $result['success']) {
            return response()->json(
                $result,
                400
            );
        }

        return response()
            ->download(
                $result['data']['file_path'],
                $result['data']['filename']
            )
            ->deleteFileAfterSend(true);
    }
}
