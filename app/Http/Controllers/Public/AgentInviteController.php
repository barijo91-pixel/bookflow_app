<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 영업자가 학원에 보낼 가입 링크를 확인·재발급.
 * 링크로 들어온 학원은 AcademyJoinController 에서 이 영업자 담당으로 연결된다.
 */
class AgentInviteController extends Controller
{
    private function authorizeAgent(): User
    {
        $user = Auth::user();
        if (! $user || $user->role_code !== 'agent') {
            abort(403, '영업자만 접근 가능합니다.');
        }
        return $user;
    }

    /** 토큰이 없으면 만들어서 반환 */
    private function ensureToken(User $agent): string
    {
        if (! empty($agent->invite_token)) {
            return $agent->invite_token;
        }
        $token = $this->newToken();
        DB::table('users')->where('id', $agent->id)->update([
            'invite_token' => $token,
            'updated_at'   => now(),
        ]);
        return $token;
    }

    private function newToken(): string
    {
        do {
            $token = Str::lower(Str::random(32));
        } while (DB::table('users')->where('invite_token', $token)->exists());
        return $token;
    }

    public function show()
    {
        $agent = $this->authorizeAgent();
        $token = $this->ensureToken($agent);
        $url   = route('academy.join', $token);

        // 이 링크로 가입한 학원들 (최근순)
        $joined = DB::table('agent_vendor_discounts as a')
            ->join('vendors as v', 'v.id', '=', 'a.vendor_id')
            ->where('a.agent_user_id', $agent->id)
            ->whereNull('v.deleted_at')
            ->orderByDesc('v.id')
            ->limit(10)
            ->get(['v.id', 'v.name', 'v.owner_name', 'v.mobile', 'v.created_at']);

        return view('public.mypage.invite_link', [
            'user'   => $agent,
            'url'    => $url,
            'joined' => $joined,
        ]);
    }

    /** 링크 재발급 — 유출됐을 때 옛 링크를 죽인다 */
    public function regenerate()
    {
        $agent = $this->authorizeAgent();
        $old   = $agent->invite_token;
        $token = $this->newToken();

        DB::table('users')->where('id', $agent->id)->update([
            'invite_token' => $token,
            'updated_at'   => now(),
        ]);

        AuditLog::log('users', $agent->id, 'regenerate_invite_token', ['had_token' => (bool) $old], null);

        return redirect()->route('my.invite.show')
            ->with('success', '새 링크가 발급되었습니다. 이전 링크는 더 이상 사용할 수 없습니다.');
    }
}
