<?php

namespace Tests\Unit\Support;

use App\Support\JsonPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use PHPUnit\Framework\TestCase;

class JsonPaginatorTest extends TestCase
{
    public function test_it_returns_only_the_shared_list_metadata(): void
    {
        $paginator = new LengthAwarePaginator(
            [['id' => 21]],
            41,
            20,
            2,
            ['path' => '/api/list'],
        );

        self::assertSame([
            'data' => [['id' => 21]],
            'current_page' => 2,
            'last_page' => 3,
            'per_page' => 20,
            'total' => 41,
            'from' => 21,
            'to' => 21,
        ], JsonPaginator::from($paginator));
    }
}
