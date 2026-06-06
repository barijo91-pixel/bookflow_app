<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 모든 HTTP 응답에 보안 헤더를 추가합니다.
 *
 * - HSTS: HTTPS 강제 (브라우저 기억) — 운영 환경에서만
 * - X-Frame-Options: 클릭재킹 방어
 * - X-Content-Type-Options: MIME 스니핑 방어
 * - Referrer-Policy: 외부 사이트로 갈 때 URL 정보 최소화
 * - Permissions-Policy: 불필요한 권한 차단 (카메라/마이크/위치 등)
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // 운영 환경(HTTPS)에서만 HSTS 적용 — 로컬 개발용 http 망가지지 않도록
        if (app()->environment('production') && $request->isSecure()) {
            // 1년간 HTTPS 강제 + 서브도메인 포함 + preload 리스트 등록 가능
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // 클릭재킹(iframe 끼워넣기) 방어
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // 브라우저가 Content-Type을 추측하지 못하게 차단 (XSS 보조 방어)
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // 외부 사이트로 이동 시 URL 노출 최소화
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // 바코드 스캔 등에서 카메라는 자체 사용 → camera만 self 허용, 나머지 차단
        $response->headers->set(
            'Permissions-Policy',
            'camera=(self), microphone=(), geolocation=(), payment=(self), usb=()'
        );

        return $response;
    }
}
