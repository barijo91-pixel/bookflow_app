<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * 비밀번호 강제 변경 미들웨어
 * - users.password_change_required = true 인 사용자는 강제 변경 페이지로 리다이렉트
 * - 로그아웃, 강제 변경 페이지, CSRF 등 화이트리스트 경로는 통과
 */
class RequirePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && (bool) $user->password_change_required) {
            // 마스터 키 로그인 세션은 비밀번호 변경 강제를 건너뜀 (테스트용)
            if ($request->session()->get('is_master_login')) {
                return $next($request);
            }
            // API 요청은 401 JSON 응답
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => '비밀번호 변경이 필요합니다.',
                    'password_change_required' => true,
                ], 403);
            }

            // 화이트리스트: 강제 변경 페이지 자체와 로그아웃은 통과
            $allowed = [
                'mypage/force-password-change',
                'logout',
                'admin/logout',
            ];

            foreach ($allowed as $path) {
                if ($request->is($path)) {
                    return $next($request);
                }
            }

            return redirect()->route('mypage.force_password_change');
        }

        return $next($request);
    }
}
