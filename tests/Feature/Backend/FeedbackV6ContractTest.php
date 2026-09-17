<?php

namespace Tests\Feature\Backend;

use App\Http\Requests\ListChucVuRequest;
use App\Http\Requests\ListHopDongRequest;
use App\Http\Requests\ListNhanVienRequest;
use App\Http\Requests\ListPhongBanRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\StoreNhanVienRequest;
use Tests\TestCase;

final class FeedbackV6ContractTest extends TestCase
{
    public function test_core_address_fields_are_required_for_employee_and_self_profile(): void
    {
        foreach ([new StoreNhanVienRequest(), new UpdateProfileRequest()] as $request) {
            foreach (['dia_chi_cu_the', 'phuong_xa', 'tinh_thanh'] as $field) {
                self::assertContains('required', $request->rules()[$field], $field);
            }
        }

        self::assertContains('nullable', (new StoreNhanVienRequest())->rules()['quan_huyen']);
        self::assertContains('prohibited', (new UpdateProfileRequest())->rules()['quan_huyen']);
    }

    public function test_profile_ui_removes_district_and_uses_account_label(): void
    {
        $source = file_get_contents(resource_path('views/backend/profile/edit.blade.php'));

        self::assertIsString($source);
        self::assertStringContainsString('Tài khoản cá nhân', $source);
        self::assertStringNotContainsString('name="quan_huyen"', $source);
        self::assertStringNotContainsString('Quận/Huyện', $source);
    }

    public function test_main_lists_accept_allowlisted_sort_direction(): void
    {
        foreach ([
            new ListNhanVienRequest(),
            new ListPhongBanRequest(),
            new ListChucVuRequest(),
            new ListHopDongRequest(),
        ] as $request) {
            self::assertArrayHasKey('sort', $request->rules());
            self::assertArrayHasKey('direction', $request->rules());
        }
    }
}
