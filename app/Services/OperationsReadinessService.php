<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * 운영 준비도 점검 — 실제 운영 진입 전 필수/권장 항목 자동 체크
 *
 * 카테고리:
 *  - critical: 미설정 시 운영 불가 (회사 정보, 첫 총판 등)
 *  - important: 운영은 가능하나 권장 (알림톡, PG)
 *  - cleanup:   데모 데이터 잔존 여부
 */
class OperationsReadinessService
{
    /**
     * 전체 체크리스트 반환
     */
    public static function check(): array
    {
        return [
            'critical'  => self::checkCritical(),
            'important' => self::checkImportant(),
            'cleanup'   => self::checkCleanup(),
        ];
    }

    /**
     * 요약 통계 (대시보드용)
     */
    public static function summary(): array
    {
        $all = self::check();
        $flat = array_merge($all['critical'], $all['important'], $all['cleanup']);
        return [
            'total'        => count($flat),
            'done'         => count(array_filter($flat, fn($s) => $s['done'])),
            'critical_undone' => count(array_filter($all['critical'], fn($s) => ! $s['done'])),
            'important_undone' => count(array_filter($all['important'], fn($s) => ! $s['done'])),
            'cleanup_needed'   => count(array_filter($all['cleanup'], fn($s) => ! $s['done'])),
        ];
    }

    private static function checkCritical(): array
    {
        return [
            [
                'key'   => 'company_name',
                'label' => '회사 이름 등록',
                'desc'  => '사이트 설정 → 회사 정보',
                'done'  => ! empty(setting('company_name')),
                'href'  => route('admin.settings.edit') . '?group=company',
            ],
            [
                'key'   => 'business_no',
                'label' => '사업자등록번호',
                'desc'  => '운영 필수 — 세금계산서/정산',
                'done'  => ! empty(setting('business_no')),
                'href'  => route('admin.settings.edit') . '?group=company',
            ],
            [
                'key'   => 'company_phone',
                'label' => '대표 연락처',
                'desc'  => '학원·학부모 문의 대응용',
                'done'  => ! empty(setting('company_phone')),
                'href'  => route('admin.settings.edit') . '?group=company',
            ],
            [
                'key'   => 'real_distributor',
                'label' => '실제 총판 계정 1개 이상',
                'desc'  => '데모 외 실제 총판 (이메일/계좌 등록)',
                'done'  => DB::table('users')
                    ->where('role_code', 'distributor')
                    ->where('status_code', 'active')
                    ->where('login_id', 'not like', 'dist%')
                    ->whereNotNull('bank_account')
                    ->exists(),
                'href'  => route('admin.users.index') . '?role=distributor',
            ],
            [
                'key'   => 'admin_pw_changed',
                'label' => '관리자 비밀번호 변경',
                'desc'  => '시드 기본값(test1234) 사용 중이면 보안 위험',
                'done'  => self::checkAdminPasswordChanged(),
                'href'  => route('admin.users.index') . '?role=admin',
            ],
        ];
    }

    private static function checkImportant(): array
    {
        return [
            [
                'key'   => 'aligo_key',
                'label' => '알리고 알림톡 API Key',
                'desc'  => '학원/학부모 알림톡 발송 (미설정 시 알림 SKIP)',
                'done'  => ! empty(setting('aligo_api_key')) && ! empty(setting('aligo_user_id')),
                'href'  => route('admin.settings.edit') . '?group=integration',
            ],
            [
                'key'   => 'portone_active',
                'label' => 'PortOne PG 활성화',
                'desc'  => '학부모 카드 결제 (미설정 시 mock 결제로 fallback)',
                'done'  => PortOneService::isActive(),
                'href'  => route('admin.settings.edit') . '?group=integration',
            ],
            [
                'key'   => 'aladin_ttb',
                'label' => '알라딘 TTB Key',
                'desc'  => 'ISBN 자동 도서 조회 (미설정 시 수동 입력)',
                'done'  => ! empty(setting('aladin_ttb_key')),
                'href'  => route('admin.settings.edit') . '?group=integration',
            ],
            [
                'key'   => 'seo_meta',
                'label' => 'SEO 메타 정보',
                'desc'  => '구글/네이버 검색 노출',
                'done'  => ! empty(setting('meta_title')) && ! empty(setting('meta_description')),
                'href'  => route('admin.settings.edit') . '?group=seo',
            ],
            [
                'key'   => 'real_books',
                'label' => '실제 도서 등록 (5권 이상)',
                'desc'  => '데모 외 실제 도서',
                'done'  => DB::table('books')->whereNull('deleted_at')
                    ->where('isbn', 'not like', '8888%') // 데모 ISBN 제외
                    ->count() >= 5,
                'href'  => route('admin.books.index'),
            ],
        ];
    }

    private static function checkCleanup(): array
    {
        // 데모 사용자 (login_id 패턴 기반)
        $demoUsers = DB::table('users')->where(function ($q) {
            $q->where('login_id', 'like', 'dist%')
              ->orWhere('login_id', 'like', 'agent%')
              ->orWhere('login_id', 'like', 'academy%')
              ->orWhere('login_id', 'in', ['sysadmin00']);
        })->whereNull('deleted_at')->count();

        // 데모 주문 (시드된 주문 — title_snapshot 에 시드 도서명 포함되면)
        $demoOrders = DB::table('orders')->whereNull('deleted_at')
            ->where(function ($q) {
                $q->whereIn('agent_user_id', function ($sub) {
                    $sub->select('id')->from('users')->where('login_id', 'like', 'agent%');
                });
            })->count();

        return [
            [
                'key'   => 'no_demo_users',
                'label' => '데모 사용자 정리',
                'desc'  => '시드된 데모 계정 (agent1, academy1 등) ' . $demoUsers . '명 잔존',
                'done'  => $demoUsers === 0,
                'href'  => route('admin.users.index'),
            ],
            [
                'key'   => 'no_demo_orders',
                'label' => '데모 주문 정리',
                'desc'  => $demoOrders . '건 잔존 (데모 영업자 명의)',
                'done'  => $demoOrders === 0,
                'href'  => route('admin.orders.index'),
            ],
        ];
    }

    /**
     * 관리자 비밀번호가 시드 기본값(test1234)인지 확인
     */
    private static function checkAdminPasswordChanged(): bool
    {
        $admin = DB::table('users')->where('role_code', 'admin')
            ->whereNull('deleted_at')->first();
        if (! $admin) return false;
        return ! \Illuminate\Support\Facades\Hash::check('test1234', $admin->password);
    }
}
