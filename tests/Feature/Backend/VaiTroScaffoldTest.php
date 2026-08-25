<?php

namespace Tests\Feature\Backend;

use App\Http\Requests\StoreVaiTroRequest;
use Tests\TestCase;

final class VaiTroScaffoldTest extends TestCase
{
    public function test_role_request_has_server_side_name_validation(): void
    {
        $request = new StoreVaiTroRequest;
        $this->assertContains('required', $request->rules()['ten_vt']);
        $this->assertContains('unique:vai_tro,ten_vt', $request->rules()['ten_vt']);
    }
}
