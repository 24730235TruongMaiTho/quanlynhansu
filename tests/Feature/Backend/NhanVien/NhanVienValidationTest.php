<?php

namespace Tests\Feature\Backend\NhanVien;

use App\Contracts\NhanVienRepositoryContract;
use App\Exceptions\NhanVienDomainException;
use App\Http\Requests\StoreNhanVienRequest;
use App\Http\Requests\UpdateNhanVienRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Mockery;
use Tests\Support\CreatesEmployeeFeatureSchema;
use Tests\TestCase;

class NhanVienValidationTest extends TestCase
{
    use CreatesEmployeeFeatureSchema;
    protected function setUp(): void
    {
        parent::setUp();

        $this->createEmployeeFeatureSchema();
        $this->bindCurrentEmployee((object) ['ma_nv' => '00001', 'ma_tt' => 2]);

        Route::post('/_tests/nhan-vien', function (StoreNhanVienRequest $request) {
            return response()->json(array_merge(
                $request->safe()->except('anh_dai_dien'),
                ['avatar_uploaded' => $request->validated('anh_dai_dien') instanceof UploadedFile],
            ));
        });
        Route::put('/_tests/nhan-vien/{ma_nv}', function (UpdateNhanVienRequest $request) {
            return response()->json($request->safe()->except('anh_dai_dien'));
        });
    }

    protected function tearDown(): void
    {
        $this->dropEmployeeFeatureSchema();

        parent::tearDown();
    }

    public function test_store_normalizes_unicode_strings_email_cccd_and_numeric_identifiers(): void
    {
        $response = $this->postJson('/_tests/nhan-vien', $this->validPayload([
            'ho_ten' => '  Nguyễn An  ',
            'gioi_tinh' => '0',
            'email' => '  NHANVIEN@EXAMPLE.TEST  ',
            'ma_pb' => '1',
            'ma_cv' => '1',
            'cccd' => '  001200000001  ',
            'ma_tt' => '1',
            'dia_chi_cu_the' => '  1 Nguyễn Trãi  ',
        ]));

        $response->assertOk()->assertJson([
            'ho_ten' => 'Nguyễn An',
            'gioi_tinh' => 0,
            'email' => 'nhanvien@example.test',
            'ma_pb' => 1,
            'ma_cv' => 1,
            'cccd' => '001200000001',
            'ma_tt' => 1,
            'dia_chi_cu_the' => '1 Nguyễn Trãi',
        ]);
    }

    public function test_store_accepts_exact_eighteen_and_rejects_the_day_before(): void
    {
        $this->postJson('/_tests/nhan-vien', $this->validPayload([
            'ngay_sinh' => '2008-08-12',
            'ngay_vao_lam' => '2026-08-12',
        ]))->assertOk();

        $this->postJson('/_tests/nhan-vien', $this->validPayload([
            'ngay_sinh' => '2008-08-13',
            'ngay_vao_lam' => '2026-08-12',
        ]))->assertUnprocessable()->assertJsonValidationErrors('ngay_sinh');
    }

    public function test_invalid_iso_dates_are_owned_by_date_validation_without_crashing_age_rule(): void
    {
        foreach ([
            ['ngay_sinh' => '2024-02-31'],
            ['ngay_vao_lam' => '31/12/2026'],
        ] as $invalid) {
            $this->postJson('/_tests/nhan-vien', $this->validPayload($invalid))
                ->assertUnprocessable()
                ->assertJsonValidationErrors(array_key_first($invalid));
        }
    }

    public function test_store_email_is_unique_after_case_and_whitespace_normalization(): void
    {
        $this->insertEmployeeIdentity();

        $this->postJson('/_tests/nhan-vien', $this->validPayload([
            'email' => '  EXISTING@EXAMPLE.TEST ',
        ]))->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_store_email_uniqueness_is_case_insensitive_independent_of_database_collation(): void
    {
        $this->insertEmployeeIdentity(email: 'Existing@Example.Test');

        $this->postJson('/_tests/nhan-vien', $this->validPayload([
            'email' => 'existing@example.test',
        ]))->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_store_cccd_is_unique_after_whitespace_normalization(): void
    {
        $this->insertEmployeeIdentity();

        $this->postJson('/_tests/nhan-vien', $this->validPayload([
            'cccd' => ' 001200000099 ',
        ]))->assertUnprocessable()->assertJsonValidationErrors('cccd');
    }

    public function test_store_accepts_working_probation_and_intern_status_symbols(): void
    {
        foreach ([1, 2, 3] as $status) {
            $this->postJson('/_tests/nhan-vien', $this->validPayload(['ma_tt' => $status]))
                ->assertOk();
        }

        $this->postJson('/_tests/nhan-vien', $this->validPayload(['ma_tt' => 4]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ma_tt');

        foreach ([5, 6] as $status) {
            $this->postJson('/_tests/nhan-vien', $this->validPayload(['ma_tt' => $status]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('ma_tt');
        }
    }

    public function test_store_rejects_each_missing_lookup(): void
    {
        foreach (['ma_pb', 'ma_cv', 'ma_tt'] as $field) {
            $this->postJson('/_tests/nhan-vien', $this->validPayload([$field => 999]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors($field);
        }
    }

    public function test_phone_cccd_gender_and_email_formats_are_enforced(): void
    {
        foreach ([
            ['sdt' => '1900123456'],
            ['sdt' => '090123456'],
            ['sdt' => '090123456a'],
            ['cccd' => '00120000001'],
            ['cccd' => '00120000000a'],
            ['gioi_tinh' => 2],
            ['email' => 'not-an-email'],
        ] as $invalid) {
            $field = array_key_first($invalid);
            $this->postJson('/_tests/nhan-vien', $this->validPayload($invalid))
                ->assertUnprocessable()
                ->assertJsonValidationErrors($field);
        }
    }

    public function test_profile_and_address_length_limits_are_enforced(): void
    {
        foreach ([
            'ho_ten' => 51,
            'dan_toc' => 51,
            'noi_cap_cccd' => 51,
            'hoc_van' => 51,
            'email' => 101,
            'dia_chi_cu_the' => 256,
            'phuong_xa' => 101,
            'quan_huyen' => 101,
            'tinh_thanh' => 101,
        ] as $field => $length) {
            $value = $field === 'email'
                ? str_repeat('a', 89).'@example.test'
                : str_repeat('ă', $length);

            $this->postJson('/_tests/nhan-vien', $this->validPayload([$field => $value]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors($field);
        }
    }

    public function test_all_four_address_parts_are_required_after_trimming(): void
    {
        foreach (['dia_chi_cu_the', 'phuong_xa', 'quan_huyen', 'tinh_thanh'] as $field) {
            $this->postJson('/_tests/nhan-vien', $this->validPayload([$field => '   ']))
                ->assertUnprocessable()
                ->assertJsonValidationErrors($field);
        }
    }

    public function test_all_four_address_parts_may_be_omitted_together(): void
    {
        $this->postJson('/_tests/nhan-vien', $this->validPayload([
            'dia_chi_cu_the' => null,
            'phuong_xa' => null,
            'quan_huyen' => null,
            'tinh_thanh' => null,
        ]))->assertOk();
    }

    public function test_avatar_accepts_supported_images_and_rejects_wrong_type_or_oversize(): void
    {
        foreach (['jpg', 'png', 'webp'] as $extension) {
            $this->post('/_tests/nhan-vien', $this->validPayload([
                'anh_dai_dien' => $this->fakeImage("avatar.{$extension}"),
            ]), ['Accept' => 'application/json'])
                ->assertOk()
                ->assertJson(['avatar_uploaded' => true]);
        }

        $this->post('/_tests/nhan-vien', $this->validPayload([
            'anh_dai_dien' => UploadedFile::fake()->create('avatar.gif', 100, 'image/gif'),
        ]), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('anh_dai_dien');

        $this->post('/_tests/nhan-vien', $this->validPayload([
            'anh_dai_dien' => $this->fakeImage('avatar.jpg')->size(2049),
        ]), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('anh_dai_dien');
    }

    public function test_store_rejects_every_system_owned_field_and_avatar_removal_flag(): void
    {
        foreach ([
            'ma_nv' => '99999',
            'ma_vt' => 9,
            'mat_khau' => 'plaintext',
            'mat_khau_hash' => 'crafted-hash',
            'ngay_nghi_viec' => '2026-08-12',
            'xoa_anh_dai_dien' => true,
        ] as $field => $value) {
            $this->postJson('/_tests/nhan-vien', $this->validPayload([$field => $value]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors($field);
        }
    }

    public function test_store_requires_every_system_owned_field_to_be_entirely_absent(): void
    {
        foreach (['ma_nv', 'ma_vt', 'mat_khau', 'mat_khau_hash', 'ngay_nghi_viec', 'xoa_anh_dai_dien'] as $field) {
            foreach (['', null, []] as $value) {
                $this->postJson('/_tests/nhan-vien', $this->validPayload([$field => $value]))
                    ->assertUnprocessable()
                    ->assertJsonValidationErrors($field);
            }
        }

        $response = $this->postJson('/_tests/nhan-vien', $this->validPayload())->assertOk();

        foreach (['ma_nv', 'ma_vt', 'mat_khau', 'mat_khau_hash', 'ngay_nghi_viec', 'xoa_anh_dai_dien'] as $field) {
            $response->assertJsonMissingPath($field);
        }
    }

    public function test_update_rejects_every_system_owned_field(): void
    {
        foreach ([
            'ma_nv' => '99999',
            'ma_vt' => 9,
            'mat_khau' => 'plaintext',
            'mat_khau_hash' => 'crafted-hash',
            'ngay_nghi_viec' => '2026-08-12',
        ] as $field => $value) {
            $this->putJson('/_tests/nhan-vien/00001', $this->validPayload([$field => $value]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors($field);
        }
    }

    public function test_update_requires_every_system_owned_field_to_be_entirely_absent(): void
    {
        foreach (['ma_nv', 'ma_vt', 'mat_khau', 'mat_khau_hash', 'ngay_nghi_viec'] as $field) {
            foreach (['', null, []] as $value) {
                $this->putJson('/_tests/nhan-vien/00001', $this->validPayload([$field => $value]))
                    ->assertUnprocessable()
                    ->assertJsonValidationErrors($field);
            }
        }

        $response = $this->putJson('/_tests/nhan-vien/00001', $this->validPayload())->assertOk();

        foreach (['ma_nv', 'ma_vt', 'mat_khau', 'mat_khau_hash', 'ngay_nghi_viec'] as $field) {
            $response->assertJsonMissingPath($field);
        }
    }

    public function test_update_unique_rules_ignore_only_the_exact_route_employee_code(): void
    {
        $this->insertEmployeeIdentity(email: 'Existing@Example.Test');

        $sameEmployee = $this->validPayload([
            'email' => ' EXISTING@EXAMPLE.TEST ',
            'cccd' => ' 001200000099 ',
        ]);

        $this->putJson('/_tests/nhan-vien/00001', $sameEmployee)->assertOk();
        $this->putJson('/_tests/nhan-vien/00002', $sameEmployee)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'cccd']);
    }

    public function test_update_email_case_insensitive_collision_ignores_only_the_same_employee(): void
    {
        $this->insertEmployeeIdentity(email: 'Existing@Example.Test');

        $payload = $this->validPayload([
            'email' => 'existing@example.test',
            'cccd' => '001200000099',
        ]);

        $this->putJson('/_tests/nhan-vien/00001', $payload)->assertOk();
        $this->putJson('/_tests/nhan-vien/00002', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_update_enforces_active_and_terminated_lifecycle_invariants(): void
    {
        $this->bindCurrentEmployee((object) ['ma_nv' => '00001', 'ma_tt' => 2]);
        $this->putJson('/_tests/nhan-vien/00001', $this->validPayload(['ma_tt' => 2]))->assertOk();
        $this->putJson('/_tests/nhan-vien/00001', $this->validPayload(['ma_tt' => 4]))
            ->assertUnprocessable()->assertJsonValidationErrors('ma_tt');

        $this->bindCurrentEmployee((object) ['ma_nv' => '00001', 'ma_tt' => 4]);
        $this->putJson('/_tests/nhan-vien/00001', $this->validPayload(['ma_tt' => 4]))->assertOk();
        $this->putJson('/_tests/nhan-vien/00001', $this->validPayload(['ma_tt' => 1]))
            ->assertUnprocessable()->assertJsonValidationErrors('ma_tt');

        foreach ([
            4 => [5, 6],
            5 => [4, 6],
            6 => [4, 5],
        ] as $currentStatus => $otherTerminalStatuses) {
            $this->bindCurrentEmployee((object) ['ma_nv' => '00001', 'ma_tt' => $currentStatus]);
            foreach ($otherTerminalStatuses as $targetStatus) {
                $this->putJson('/_tests/nhan-vien/00001', $this->validPayload(['ma_tt' => $targetStatus]))
                    ->assertUnprocessable()->assertJsonValidationErrors('ma_tt');
            }
        }
    }

    public function test_update_requires_all_address_parts_or_none(): void
    {
        $this->putJson('/_tests/nhan-vien/00001', $this->validPayload([
            'phuong_xa' => '   ',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phuong_xa');
    }

    public function test_update_returns_safe_not_found_before_validation_when_route_employee_does_not_exist(): void
    {
        $this->bindCurrentEmployee(null);

        $this->putJson('/_tests/nhan-vien/99999', $this->validPayload())
            ->assertNotFound()
            ->assertJsonMissingValidationErrors();
    }

    public function test_update_authorizes_target_before_base_validation_and_reuses_that_lookup(): void
    {
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('find')->once()->with('00001')->andReturn((object) [
            'ma_nv' => '00001',
            'ma_tt' => 2,
            'ma_vt' => 5,
            'ma_vt' => 5,
            'ma_tt' => 2,
        ]);
        $this->app->instance(NhanVienRepositoryContract::class, $repository);

        $this->putJson('/_tests/nhan-vien/00001', $this->validPayload(['email' => 'invalid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_update_maps_exact_repository_not_found_domain_error_to_safe_404(): void
    {
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('find')->andThrow(new NhanVienDomainException(
            'SQLSTATE[HY000]: chi tiết nội bộ không được lộ.',
            'NV_NOT_FOUND',
        ));
        $this->app->instance(NhanVienRepositoryContract::class, $repository);

        $this->putJson('/_tests/nhan-vien/99999', $this->validPayload())
            ->assertNotFound()
            ->assertJsonMissingValidationErrors()
            ->assertDontSee('SQLSTATE');
    }

    public function test_update_rethrows_unknown_repository_domain_error_fail_closed(): void
    {
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('find')->andThrow(new NhanVienDomainException(
            'Chi tiết hạ tầng không được chuyển thành not-found.',
            'NV_DATABASE_ERROR',
        ));
        $this->app->instance(NhanVienRepositoryContract::class, $repository);
        $this->withoutExceptionHandling();

        $this->expectException(NhanVienDomainException::class);
        $this->expectExceptionMessage('Chi tiết hạ tầng không được chuyển thành not-found.');

        $this->putJson('/_tests/nhan-vien/00001', $this->validPayload());
    }

    public function test_update_accepts_string_zero_for_boolean_flag_and_rejects_avatar_remove_conflict(): void
    {
        $this->put('/_tests/nhan-vien/00001', $this->validPayload([
            'xoa_anh_dai_dien' => '0',
            'anh_dai_dien' => $this->fakeImage('avatar.jpg'),
        ]), ['Accept' => 'application/json'])->assertOk();

        $this->put('/_tests/nhan-vien/00001', $this->validPayload([
            'xoa_anh_dai_dien' => true,
            'anh_dai_dien' => $this->fakeImage('avatar.jpg'),
        ]), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('xoa_anh_dai_dien');
    }

    private function validPayload(array $overrides = []): array
    {
        return array_replace([
            'ho_ten' => 'Nguyễn An',
            'ngay_sinh' => '2000-08-12',
            'gioi_tinh' => 1,
            'sdt' => '0901234567',
            'email' => 'nhanvien@example.test',
            'ngay_vao_lam' => '2026-08-12',
            'ma_pb' => 1,
            'ma_cv' => 1,
            'dan_toc' => 'Kinh',
            'cccd' => '001200000001',
            'noi_cap_cccd' => 'Cục CSQLHC',
            'hoc_van' => 'Đại học',
            'ma_tt' => 1,
            'dia_chi_cu_the' => '1 Nguyễn Trãi',
            'phuong_xa' => 'Bến Thành',
            'quan_huyen' => 'Quận 1',
            'tinh_thanh' => 'TP Hồ Chí Minh',
        ], $overrides);
    }

    private function bindCurrentEmployee(?object $employee): void
    {
        if ($employee !== null && ! property_exists($employee, 'ma_vt')) {
            $employee->ma_vt = 5;
        }
        if ($employee !== null && ! property_exists($employee, 'ma_vt')) {
            $employee->ma_vt = 5;
        }
        if ($employee !== null && ! property_exists($employee, 'ma_tt')) {
            $employee->ma_tt = 2;
        }

        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('find')->andReturn($employee);
        $this->app->instance(NhanVienRepositoryContract::class, $repository);
    }

    private function fakeImage(string $name): UploadedFile
    {
        $images = [
            'jpg' => '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABBQJ//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPwF//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPwF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQAGPwJ//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPyF//9oADAMBAAIAAwAAABAf/8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPxB//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPxB//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxB//9k=',
            'png' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            'webp' => 'UklGRiIAAABXRUJQVlA4IBYAAAAwAQCdASoBAAEAAUAmJaQAA3AA/v3AgAA=',
        ];
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $content = base64_decode(
            $images[$extension] ?? $images['png'],
            true,
        );

        return UploadedFile::fake()->createWithContent($name, $content);
    }
}
