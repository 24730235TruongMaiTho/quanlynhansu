<?php

namespace App\Http\Controllers\Backend;

use App\Contracts\NhanVienServiceContract;
use App\Exceptions\NhanVienDomainException;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Support\NhanVienAvatarPath;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

final class ProfileController extends Controller
{
    public function __construct(private NhanVienServiceContract $employees) {}

    public function edit(): View
    {
        $employee = $this->currentEmployee();

        return view('backend.profile.edit', [
            'employee' => $employee,
            'avatarUrl' => $this->avatarUrl($employee),
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $maNv = (string) auth()->id();

        try {
            $this->employees->updateOwnProfile($maNv, $request->validated());
        } catch (NhanVienDomainException $exception) {
            return back()
                ->withInput($request->safe()->except(['anh_dai_dien']))
                ->withErrors([
                    $exception->field ?? 'profile' => $exception->getMessage(),
                ]);
        } catch (Throwable) {
            return back()
                ->withInput($request->safe()->except(['anh_dai_dien']))
                ->withErrors([
                    'profile' => 'Không thể cập nhật hồ sơ cá nhân lúc này. Vui lòng thử lại sau.',
                ]);
        }

        return redirect()
            ->route('backend.profile.edit')
            ->with('success', 'Đã cập nhật hồ sơ cá nhân.');
    }

    public function password(): View
    {
        return view('backend.profile.password');
    }

    public function updatePassword(ChangePasswordRequest $request): RedirectResponse
    {
        $maNv = (string) auth()->id();

        try {
            $this->employees->changeOwnPassword(
                $maNv,
                (string) $request->validated('mat_khau_hien_tai'),
                (string) $request->validated('mat_khau_moi'),
            );
        } catch (NhanVienDomainException $exception) {
            return back()
                ->withErrors([
                    $exception->field ?? 'password' => $exception->getMessage(),
                ]);
        } catch (Throwable) {
            return back()
                ->withErrors([
                    'password' => 'Không thể đổi mật khẩu lúc này. Vui lòng thử lại sau.',
                ]);
        }

        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('backend.profile.password.edit')
            ->with('success', 'Đã đổi mật khẩu.');
    }

    private function currentEmployee(): object
    {
        return $this->employees->findOrFail((string) auth()->id());
    }

    private function avatarUrl(object $employee): ?string
    {
        try {
            $ownedAvatar = (new NhanVienAvatarPath)->assertOwnedFile($employee->anh_dai_dien ?? null);

            return $ownedAvatar === null ? null : Storage::disk('public')->url($ownedAvatar);
        } catch (Throwable) {
            return null;
        }
    }
}
