<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\DistributorScopeService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * 총판 사용자관리 — 산하(영업자 + 그 영업자 담당 학원 계정) 계정만.
 * 총판이 여러 개인 구조라 총판끼리는 서로의 사용자를 볼 수 없다.
 * 관리자 UserController 의 축소판 (승인/거절/정지/정상화/비번초기화).
 */
class DistributorUserController extends Controller
{
    private function authorizeDistributor(): User
    {
        $user = Auth::user();
        if (! $user || $user->role_code !== 'distributor') {
            abort(403, '총판만 접근 가능합니다.');
        }
        return $user;
    }

    /** 대상이 내 산하인지 + 손댈 수 있는 역할인지 */
    private function authorizeTarget(User $target): User
    {
        $me = $this->authorizeDistributor();

        // 관리자·총판 계정은 총판이 건드릴 수 없음
        if (! in_array($target->role_code, ['agent', 'academy'], true)) {
            abort(403, '영업자·학원 계정만 관리할 수 있습니다.');
        }
        if (! DistributorScopeService::ownsUser($me->id, $target->id)) {
            abort(404, '산하 계정이 아닙니다.');
        }
        return $me;
    }

    public function index(Request $request)
    {
        $me = $this->authorizeDistributor();

        $role   = $request->query('role');
        $status = $request->query('status');
        $q      = trim((string) $request->query('q'));

        $allowedSorts = ['id', 'name', 'login_id', 'role_code', 'status_code', 'last_login_at'];
        $sort = $request->query('sort', 'id');
        $dir  = $request->query('dir', 'desc');
        if (! in_array($sort, $allowedSorts, true)) $sort = 'id';
        if (! in_array($dir, ['asc', 'desc'], true)) $dir = 'desc';

        $scopedIds = DistributorScopeService::userIds($me->id);

        $query = User::query()->whereIn('id', $scopedIds)->orderBy($sort, $dir);
        if ($sort !== 'id') $query->orderByDesc('id');

        if ($role)   $query->where('role_code', $role);
        if ($status) $query->where('status_code', $status);
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                  ->orWhere('login_id', 'like', "%{$q}%")
                  ->orWhere('phone', 'like', "%{$q}%");
            });
        }

        $users = $query->paginate(50)->withQueryString();

        // 소속(학원 계정 → 학원명) 일괄 조회 — N+1 방지
        $affiliations = [];
        $ids = $users->pluck('id')->all();
        if ($ids) {
            foreach (DB::table('vendor_users as vu')
                         ->join('vendors as v', 'v.id', '=', 'vu.vendor_id')
                         ->whereIn('vu.user_id', $ids)
                         ->whereNull('v.deleted_at')
                         ->get(['vu.user_id', 'v.name']) as $row) {
                $affiliations[$row->user_id] = $row->name;
            }
        }

        // 승인 대기 건수 (배지용)
        $pendingCount = User::whereIn('id', $scopedIds)->where('status_code', 'pending')->count();

        $statusOptions = DB::table('codes')->where('group_code', 'user_status')->orderBy('sort_order')->get();

        return view('public.mypage.users', compact(
            'me', 'users', 'affiliations', 'role', 'status', 'q', 'sort', 'dir',
            'statusOptions', 'pendingCount'
        ));
    }

    /** 계정 정보 수정 폼 (영업자·학원 공통 — 아이디·역할은 변경 불가) */
    public function edit(User $user)
    {
        $me = $this->authorizeTarget($user);

        // 학원 계정이면 소속 학원(수정은 학원 상세에서)
        $vendor = null;
        if ($user->role_code === 'academy') {
            $vendor = DB::table('vendor_users as vu')
                ->join('vendors as v', 'v.id', '=', 'vu.vendor_id')
                ->where('vu.user_id', $user->id)
                ->whereNull('v.deleted_at')
                ->first(['v.id', 'v.name']);
        }

        return view('public.mypage.user_edit', [
            'user'   => $me,        // 레이아웃(사이드바)용 로그인 사용자
            'target' => $user,
            'vendor' => $vendor,
        ]);
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeTarget($user);

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:80'],
            'phone'       => ['required', 'string', 'max:20'],
            'email'       => ['nullable', 'email', 'max:150'],
            'status_code' => ['required', 'in:active,suspended'],
        ], [], ['name' => '이름', 'phone' => '휴대폰']);

        $before = $user->only(['name', 'phone', 'email', 'status_code']);
        $user->update([
            'name'        => $data['name'],
            'phone'       => preg_replace('/[^0-9]/', '', (string) $data['phone']),
            'email'       => $data['email'] ?? null,
            'status_code' => $data['status_code'],
        ]);

        AuditLog::log('users', $user->id, 'distributor_update_user', $before,
            $user->only(['name', 'phone', 'email', 'status_code']));

        return redirect()->route('my.users.index')
            ->with('success', "{$user->name}({$user->login_id}) 계정 정보가 수정되었습니다.");
    }

    public function approve(User $user, NotificationService $notify)
    {
        $this->authorizeTarget($user);

        $before = ['status_code' => $user->status_code];
        $user->status_code = 'active';
        $user->approved_by = Auth::id();
        $user->approved_at = now();
        $user->save();

        AuditLog::log('users', $user->id, 'distributor_approve', $before, ['status_code' => 'active']);

        $notify->send('user.approval_result', ['name' => $user->name, 'result' => '승인'], [
            ['type' => 'user', 'id' => $user->id, 'phone' => $user->phone, 'email' => $user->email],
        ]);

        return back()->with('success', "{$user->name}({$user->login_id}) 승인 완료");
    }

    public function reject(User $user, NotificationService $notify)
    {
        $this->authorizeTarget($user);

        $before = ['status_code' => $user->status_code];
        $user->status_code = 'terminated';
        $user->save();

        AuditLog::log('users', $user->id, 'distributor_reject', $before, ['status_code' => 'terminated']);

        $notify->send('user.approval_result', ['name' => $user->name, 'result' => '거절'], [
            ['type' => 'user', 'id' => $user->id, 'phone' => $user->phone, 'email' => $user->email],
        ]);

        return back()->with('success', "{$user->name}({$user->login_id}) 거절 처리됨");
    }

    public function suspend(User $user)
    {
        $this->authorizeTarget($user);

        $before = ['status_code' => $user->status_code];
        $user->status_code = 'suspended';
        $user->save();
        AuditLog::log('users', $user->id, 'distributor_suspend', $before, ['status_code' => 'suspended']);

        return back()->with('success', "{$user->name}({$user->login_id}) 일시정지");
    }

    public function activate(User $user)
    {
        $this->authorizeTarget($user);

        $before = ['status_code' => $user->status_code];
        $user->status_code = 'active';
        $user->save();
        AuditLog::log('users', $user->id, 'distributor_activate', $before, ['status_code' => 'active']);

        return back()->with('success', "{$user->name}({$user->login_id}) 정상화");
    }

    public function resetPassword(User $user)
    {
        $this->authorizeTarget($user);

        $new = $this->genPassword();
        $user->password = $new;                     // hashed cast
        $user->password_change_required = true;     // 다음 로그인 시 변경 강제
        $user->save();

        AuditLog::log('users', $user->id, 'distributor_reset_password', null, null);

        return back()
            ->with('success', "{$user->name} 비밀번호 초기화 — 새 비밀번호: {$new} (1회만 표시됨)")
            ->with('new_password', $new);
    }

    /** 영문 4 + 숫자 4 혼합 임시 비밀번호 */
    private function genPassword(int $length = 8): string
    {
        $letters = 'abcdefghjkmnpqrstuvwxyz';
        $digits  = '23456789';
        $half    = (int) floor($length / 2);
        $out = '';
        for ($i = 0; $i < $half; $i++)            $out .= $letters[random_int(0, strlen($letters) - 1)];
        for ($i = 0; $i < $length - $half; $i++)  $out .= $digits[random_int(0, strlen($digits) - 1)];
        return $out;
    }
}
