<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        if (! Auth::attempt($data, $remember)) {
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

        $user->forceFill(['last_login_at' => now()])->save();
        $request->session()->regenerate();
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
