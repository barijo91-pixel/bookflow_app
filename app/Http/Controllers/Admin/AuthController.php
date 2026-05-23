<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check() && Auth::user()->role_code === 'admin') {
            return redirect()->route('admin.dashboard');
        }
        return view('admin.auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = (bool) $request->boolean('remember');

        // === Rate Limit: 5회 실패 → 60초 잠금 ===
        $rlKey = 'admin-login:'.Str::lower($data['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($rlKey, 5)) {
            $seconds = RateLimiter::availableIn($rlKey);
            return back()->withInput($request->only('email'))->withErrors([
                'email' => "로그인 시도가 너무 많습니다. {$seconds}초 후 다시 시도해주세요.",
            ]);
        }

        if (! Auth::attempt($data, $remember)) {
            RateLimiter::hit($rlKey, 60);
            return back()->withInput($request->only('email'))->withErrors([
                'email' => '이메일 또는 비밀번호가 올바르지 않습니다.',
            ]);
        }

        $user = Auth::user();
        if ($user->role_code !== 'admin' || $user->status_code !== 'active') {
            Auth::logout();
            return back()->withInput($request->only('email'))->withErrors([
                'email' => '관리자 권한이 없거나 비활성 계정입니다.',
            ]);
        }

        RateLimiter::clear($rlKey);

        // 이전 로그인 시각을 세션에 저장 (대시보드에서 표시)
        $previousLoginAt = $user->last_login_at;
        $user->forceFill(['last_login_at' => now()])->save();

        $request->session()->regenerate();
        $request->session()->put('admin_last_activity', now()->getTimestamp());
        $request->session()->put('previous_login_at', $previousLoginAt?->toIso8601String());
        $request->session()->put('current_login_ip', $request->ip());
        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('admin.login');
    }
}
