<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * 총판이 산하 영업자 계정을 직접 등록 (Phase B-9 확장)
 * 영업자가 학원을 등록하는 AgentVendorController 와 동일한 패턴.
 */
class DistributorAgentController extends Controller
{
    /** 총판만 접근 */
    private function authorizeDistributor(): User
    {
        $user = Auth::user();
        if (! $user || $user->role_code !== 'distributor') {
            abort(403, '총판만 접근 가능합니다.');
        }
        return $user;
    }

    /** 새 영업자 등록 폼 */
    public function create()
    {
        $user = $this->authorizeDistributor();

        $bankOptions = DB::table('codes')->where('group_code', 'bank')->orderBy('sort_order')->get();

        return view('public.mypage.agent_create', compact('user', 'bankOptions'));
    }

    /** 영업자 계정 생성 + 본 총판 산하로 매핑 */
    public function store(Request $request)
    {
        $user = $this->authorizeDistributor();

        $data = $request->validate([
            'user_login_id' => ['required', 'string', 'regex:/^[a-zA-Z0-9]{6,50}$/'],
            'user_name'     => ['required', 'string', 'max:80'],
            'user_phone'    => ['required', 'string', 'max:20'],
            'user_email'    => ['nullable', 'email', 'max:150'],
            'user_password' => ['nullable', 'string', 'min:8', 'max:50'],
            // 정산·세무 정보 (선택 — 영업자 본인이 추후 정보수정에서 보완 가능)
            'business_type' => ['nullable', 'in:none,individual_simple,individual_general,corporate'],
            'business_no'   => ['nullable', 'string', 'max:20'],
            'business_name' => ['nullable', 'string', 'max:100'],
            'bank_code'     => ['nullable', 'string', 'max:10'],
            'bank_account'  => ['nullable', 'string', 'max:50'],
            'bank_holder'   => ['nullable', 'string', 'max:50'],
        ], [], [
            'user_login_id' => '로그인 아이디',
            'user_name'     => '이름',
            'user_phone'    => '휴대폰',
        ]);

        // login_id 중복 체크
        $loginId = strtolower($data['user_login_id']);
        if (DB::table('users')->where('login_id', $loginId)->exists()) {
            return back()->withInput()->withErrors([
                'user_login_id' => "아이디 '{$data['user_login_id']}'는 이미 사용 중입니다.",
            ]);
        }

        $createdUser = null;
        $plainPw     = null;

        DB::transaction(function () use ($data, $loginId, $user, &$createdUser, &$plainPw) {
            $plainPw   = ! empty($data['user_password']) ? $data['user_password'] : $this->genPassword(8);
            $userPhone = preg_replace('/[^0-9]/', '', (string) ($data['user_phone'] ?? ''));

            $createdUser = User::create([
                'login_id'    => $loginId,
                'email'       => $data['user_email'] ?? null,
                'name'        => $data['user_name'],
                'phone'       => $userPhone,
                'password'    => $plainPw, // 모델 캐스트로 해시
                'password_change_required' => true,
                'role_code'   => 'agent',
                'status_code' => 'active',
                'approved_by' => $user->id,
                'approved_at' => now(),
                // 정산·세무 (입력된 경우만 — 비우면 영업자가 추후 보완)
                'business_type' => $data['business_type'] ?? 'none',
                'business_no'   => $data['business_no'] ?? null,
                'business_name' => $data['business_name'] ?? null,
                'bank_code'     => $data['bank_code'] ?? null,
                'bank_account'  => $data['bank_account'] ?? null,
                'bank_holder'   => $data['bank_holder'] ?? null,
            ]);

            // 총판(본인) ↔ 영업자(신규) 매핑
            DB::table('user_relations')->insert([
                'parent_user_id' => $user->id,
                'child_user_id'  => $createdUser->id,
                'relation_type'  => 'distributor_agent',
                'status'         => 'active',
                'started_at'     => now()->toDateString(),
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        });

        AuditLog::log('users', $createdUser->id, 'distributor_create_agent', null, [
            'distributor_user_id' => $user->id,
            'agent_login_id'      => $loginId,
            'agent_name'          => $data['user_name'],
        ]);

        return view('public.mypage.agent_created', [
            'user'        => $user,
            'createdUser' => [
                'login_id' => $createdUser->login_id,
                'name'     => $createdUser->name,
                'phone'    => $createdUser->phone,
                'password' => $plainPw,
            ],
        ]);
    }

    /** 사람이 읽기 쉬운 임시 비밀번호 (혼동 문자 제외) */
    private function genPassword(int $length = 8): string
    {
        $letters = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
        $digits  = '23456789';
        $half = (int) floor($length / 2);
        $pw = substr(str_shuffle(str_repeat($letters, 4)), 0, $half)
            . substr(str_shuffle(str_repeat($digits, 4)), 0, $length - $half);
        return str_shuffle($pw);
    }
}
