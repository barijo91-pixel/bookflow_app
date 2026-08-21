<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * 관리자 대행 로그인 (impersonation)
 *
 * 설계 원칙 — 비밀번호 백도어(마스터키) 방식을 쓰지 않는다.
 *  - 이미 인증된 관리자 세션에서만 전환하며, 대상 계정의 비밀번호는 사용/노출하지 않음
 *  - 원래 관리자 id 를 세션에 보관했다가 '관리자로 복귀' 시 그대로 되돌림
 *  - 시작/종료 모두 감사 로그(audit_logs) 기록
 *  - 관리자 계정(특히 슈퍼관리자)은 대행 대상에서 제외 → 권한 상승 차단
 */
class ImpersonateController extends Controller
{
    /** 세션 키 — 대행 중일 때 원래 관리자 id 보관 */
    public const SESSION_KEY = 'impersonator_id';

    /** 대행 시작 */
    public function start(Request $request, int $id)
    {
        $admin = Auth::user();

        // 관리자만, 그리고 이미 대행 중이면 중첩 금지
        if (! $admin || ! $admin->isAdmin()) {
            abort(403, '관리자만 사용할 수 있습니다.');
        }
        if ($request->session()->has(self::SESSION_KEY)) {
            return back()->with('error', '이미 대행 로그인 중입니다. 먼저 관리자로 복귀해주세요.');
        }

        $target = User::find($id);
        if (! $target) {
            return back()->with('error', '대상 사용자를 찾을 수 없습니다.');
        }
        // 관리자 계정은 대행 불가 (권한 상승·감사 회피 방지)
        if ($target->isAdmin()) {
            return back()->with('error', '관리자 계정은 대행 로그인할 수 없습니다.');
        }
        if ($target->id === $admin->id) {
            return back()->with('error', '본인 계정은 대행할 수 없습니다.');
        }
        if ($target->status_code !== 'active') {
            return back()->with('error', '활성 상태 계정만 대행할 수 있습니다. (현재: ' . $target->status_code . ')');
        }

        AuditLog::log('impersonate', $target->id, 'start', null, [
            'admin_id'       => $admin->id,
            'admin_login_id' => $admin->login_id,
            'target_login_id'=> $target->login_id,
            'target_role'    => $target->role_code,
            'ip'             => $request->ip(),
        ]);

        // 세션 고정 공격 방지 — 원래 관리자 id 는 재생성 후 다시 심는다
        $adminId = $admin->id;
        Auth::login($target);
        $request->session()->regenerate();
        $request->session()->put(self::SESSION_KEY, $adminId);

        return redirect()->route('mypage')
            ->with('success', $target->name . '(' . $target->login_id . ') 계정으로 대행 로그인했습니다.');
    }

    /** 관리자로 복귀 */
    public function stop(Request $request)
    {
        $adminId = $request->session()->get(self::SESSION_KEY);
        if (! $adminId) {
            return redirect()->route('mypage')->with('error', '대행 로그인 상태가 아닙니다.');
        }

        $admin  = User::find($adminId);
        $target = Auth::user();

        if (! $admin || ! $admin->isAdmin()) {
            // 관리자 계정이 사라졌거나 권한이 회수된 경우 — 안전하게 로그아웃
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('public.login')->with('error', '관리자 세션이 유효하지 않아 로그아웃했습니다.');
        }

        AuditLog::log('impersonate', $target->id ?? null, 'stop', null, [
            'admin_id'        => $admin->id,
            'admin_login_id'  => $admin->login_id,
            'target_login_id' => $target->login_id ?? null,
            'ip'              => $request->ip(),
        ]);

        Auth::login($admin);
        $request->session()->regenerate();
        $request->session()->forget(self::SESSION_KEY);
        // 대행 중에는 관리자가 아니라 활동시간이 갱신되지 않는다.
        // 복귀 직후 유휴 만료로 튕기지 않도록 여기서 갱신한다.
        $request->session()->put('admin_last_activity', now()->getTimestamp());

        return redirect()->route('admin.users.index')
            ->with('success', '관리자 계정으로 복귀했습니다.');
    }
}
