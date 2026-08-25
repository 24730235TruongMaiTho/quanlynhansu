<?php

namespace App\Http\Controllers\Auth;

use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

final class AuthenticatedSessionController
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        try {
            $request->ensureNotRateLimited();
        } catch (ValidationException) {
            return $this->invalidLoginResponse($request);
        }

        try {
            $authenticated = Auth::attempt([
                'dinh_danh' => $request->string('dinh_danh')->toString(),
                'password' => $request->string('mat_khau')->toString(),
            ], false);
        } catch (Throwable $exception) {
            $request->hitRateLimiter();
            try {
                Log::error('employee_authentication_failed', ['event_code' => 'NV_AUTH_FAILURE']);
            } catch (Throwable) {
                // Authentication remains fail-closed if logging is unavailable.
            }

            return $this->invalidLoginResponse($request);
        }

        if (! $authenticated) {
            $request->hitRateLimiter();

            return $this->invalidLoginResponse($request);
        }

        $request->clearRateLimiter();
        $request->session()->regenerate();

        return redirect()->intended(route('backend.tongquan.index'));
    }

    public function destroy(\Illuminate\Http\Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function invalidLoginResponse(LoginRequest $request): RedirectResponse
    {
        return redirect()->route('login')
            ->withErrors(['dinh_danh' => LoginRequest::GENERIC_ERROR])
            ->withInput($request->only('dinh_danh'));
    }
}
