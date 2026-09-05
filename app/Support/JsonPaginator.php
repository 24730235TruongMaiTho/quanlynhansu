<?php

namespace App\Support;

use Illuminate\Pagination\LengthAwarePaginator;

final class JsonPaginator
{
    /**
     * Keep list endpoints on one small, stable JSON contract.
     *
     * @return array{data: array, current_page: int, last_page: int, per_page: int, total: int, from: int, to: int}
     */
    public static function from(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => array_values($paginator->items()),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem() ?? 0,
            'to' => $paginator->lastItem() ?? 0,
        ];
    }
}
