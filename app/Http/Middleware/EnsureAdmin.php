<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        if (! $user) {
            return redirect()->route('admin.login');
        }
        if ($user->role_code !== 'admin' || $user->status_code !== 'active') {
            Auth::logout();
            return redirect()->route('admin.login')->withErrors([
                'email' => '관리자 권한이 없거나 비활성 계정입니다.',
            ]);
        }
        return $next($request);
    }
}
