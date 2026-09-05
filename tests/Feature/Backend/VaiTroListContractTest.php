<?php

namespace Tests\Feature\Backend;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class VaiTroListContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('vai_tro', static function (Blueprint $table): void {
            $table->integer('ma_vt')->primary();
            $table->string('ten_vt', 100)->unique();
            $table->string('mo_ta', 255)->nullable();
        });
        Schema::create('nhan_vien', static function (Blueprint $table): void {
            $table->string('ma_nv', 10)->primary();
            $table->integer('ma_vt')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('nhan_vien');
        Schema::dropIfExists('vai_tro');

        parent::tearDown();
    }

    public function test_role_list_filters_and_returns_page_two_with_exact_json_shape(): void
    {
        foreach (range(1, 11) as $id) {
            \DB::table('vai_tro')->insert([
                'ma_vt' => $id,
                'ten_vt' => 'Vai trò '.$id,
                'mo_ta' => null,
            ]);
        }

        $response = $this->withoutMiddleware()->getJson('/vai-tro/search?'.http_build_query([
            'ten_vt' => 'Vai trò',
            'page' => 2,
            'per_page' => 10,
            'unexpected' => 'ignored',
        ]));
        $payload = $response->getData(true);

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($payload['success']);
        self::assertSame([
            'data',
            'current_page',
            'last_page',
            'per_page',
            'total',
            'from',
            'to',
        ], array_keys($payload['data']));
        self::assertSame(2, $payload['data']['current_page']);
        self::assertSame(10, $payload['data']['per_page']);
        self::assertSame(11, $payload['data']['total']);
        self::assertSame(11, $payload['data']['from']);
        self::assertSame(11, $payload['data']['to']);
        self::assertCount(1, $payload['data']['data']);
    }

    public function test_invalid_page_size_falls_back_to_ten_without_query_injection(): void
    {
        foreach (range(1, 11) as $id) {
            \DB::table('vai_tro')->insert([
                'ma_vt' => $id,
                'ten_vt' => 'Vai trò '.$id,
                'mo_ta' => null,
            ]);
        }

        $response = $this->withoutMiddleware()->getJson('/vai-tro/data?'.http_build_query([
            'page' => 2,
            'per_page' => 5,
            'unknown' => 'must-not-appear',
        ]));
        $payload = $response->getData(true);

        self::assertSame(10, $payload['data']['per_page']);
        self::assertSame(11, $payload['data']['total']);
        self::assertCount(1, $payload['data']['data']);
    }

    public function test_role_query_failure_returns_a_safe_public_message(): void
    {
        Schema::dropIfExists('vai_tro');

        $response = $this->withoutMiddleware()->getJson('/vai-tro/data');
        $payload = $response->getData(true);

        self::assertSame(500, $response->getStatusCode());
        self::assertFalse($payload['success']);
        self::assertSame('Không thể tải danh sách vai trò.', $payload['message']);
        self::assertStringNotContainsString('no such table', $payload['message']);
    }

    public function test_non_positive_page_is_rejected_without_querying_the_database(): void
    {
        $response = $this->withoutMiddleware()->getJson('/vai-tro/data?page=0');

        self::assertSame(422, $response->getStatusCode());
        self::assertTrue($response->json('errors.page.0') !== null);
    }

}
