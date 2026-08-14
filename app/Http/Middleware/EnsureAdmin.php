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
            return redirect()->route('public.login');
        }

        // 대행 로그인 중에 관리자 URL 을 열면(북마크·이전 탭 등) 아래 로그아웃 분기로 빠져
        // 세션이 통째로 날아가 관리자로 복귀도 못 하고 다시 로그인해야 했다.
        // 대행 중임을 알아보고 화면만 되돌린다.
        if ($request->session()->has(\App\Http\Controllers\Admin\ImpersonateController::SESSION_KEY)) {
            return redirect()->route('mypage')->with('error',
                '대행 로그인 중에는 관리자 화면에 들어갈 수 없습니다. 상단의 「관리자로 복귀」를 먼저 눌러주세요.');
        }

        if ($user->role_code !== 'admin' || $user->status_code !== 'active') {
            Auth::logout();
            return redirect()->route('public.login')->withErrors([
                'login_id' => '관리자 권한이 없거나 비활성 계정입니다.',
            ]);
        }
        return $next($request);
    }
}
