<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * 관리자 세션 비활성 타임아웃
 * - 관리자(role_code=admin)는 마지막 활동 60분 이후 자동 로그아웃
 * - 일반 사용자 세션은 영향 없음 (config/session.php의 lifetime 그대로)
 */
class AdminSessionTimeout
{
    /** 비활성 허용 시간 (초) */
    const TIMEOUT_SECONDS = 3600; // 60분

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $user->role_code === 'admin') {
            $last = $request->session()->get('admin_last_activity');
            $now  = now()->getTimestamp();

            if ($last !== null && ($now - $last) > self::TIMEOUT_SECONDS) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => '세션이 만료되었습니다. 다시 로그인해주세요.',
                    ], 401);
                }
                return redirect()->route('admin.login')
                    ->withErrors(['email' => '세션이 만료되었습니다. 다시 로그인해주세요.']);
            }

            // 활동 시간 갱신
            $request->session()->put('admin_last_activity', $now);
        }

        return $next($request);
    }
}
