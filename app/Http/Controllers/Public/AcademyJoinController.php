<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * 영업자 초대 링크로 학원이 직접 가입.
 *
 * 공개 회원가입(/register)은 영업자 전용이고 학원은 영업자 대행 등록이 원칙이다.
 * 다만 "학원이 직접 넣겠다"는 경우를 위해, 영업자가 자기 링크를 보내면
 * 그 링크로 들어온 학원이 스스로 입력하고 담당 영업자로 자동 연결되게 한다.
 *
 * 결과는 영업자가 직접 등록한 것과 동일 — 학원(vendor) + 계정 + 영업자 매핑이 한 번에 생성되고
 * 바로 로그인 가능(active). 링크의 주인이 영업자이므로 대행 등록과 신뢰 수준이 같다.
 */
class AcademyJoinController extends Controller
{
    /** 신규 학원 기본 할인율 — 영업자 학원등록과 동일 정책(10%) */
    private const DEFAULT_DISCOUNT_RATE = 10;

    /** 토큰으로 영업자 찾기 (활성 영업자만) */
    private function agentByToken(string $token): User
    {
        $agent = User::where('invite_token', $token)
            ->where('role_code', 'agent')
            ->where('status_code', 'active')
            ->whereNull('deleted_at')
            ->first();

        if (! $agent) {
            abort(404, '유효하지 않은 가입 링크입니다. 담당 영업자에게 링크를 다시 요청해주세요.');
        }
        return $agent;
    }

    /** 가입 폼 */
    public function show(string $token)
    {
        $agent = $this->agentByToken($token);

        if (Auth::check()) {
            return redirect()->route('mypage');
        }

        $sidos = DB::table('regions')->where('level', 'sido')->orderBy('sort_order')->get(['id', 'name']);

        return view('public.auth.academy_join', compact('agent', 'token', 'sidos'));
    }

    /** 가입 처리 — 학원 + 계정 + 영업자 매핑 동시 생성 */
    public function store(Request $request, string $token)
    {
        $agent = $this->agentByToken($token);

        $data = $request->validate([
            'vendor_name'    => ['required', 'string', 'max:150'],
            'owner_name'     => ['required', 'string', 'max:100'],
            'phone'          => ['required', 'string', 'max:20'],
            'business_no'    => ['nullable', 'string', 'max:20'],
            'region_id'      => ['nullable', 'integer', 'exists:regions,id'],
            'address'        => ['nullable', 'string', 'max:255'],
            'address_detail' => ['nullable', 'string', 'max:255'],
            'login_id'       => ['required', 'string', 'min:6', 'max:50', 'regex:/^[a-zA-Z0-9]+$/', 'unique:users,login_id'],
            'email'          => ['nullable', 'email', 'max:150'],
            'password'       => ['required', 'confirmed', Password::min(8)->letters()->numbers(), 'max:50'],
            'agree_terms'    => ['accepted'],
        ], [
            'login_id.min'     => '아이디는 6자 이상이어야 합니다.',
            'login_id.regex'   => '아이디는 영문과 숫자만 사용 가능합니다.',
            'login_id.unique'  => '이미 사용중인 아이디입니다.',
            'password.min'     => '비밀번호는 최소 8자 이상이어야 합니다.',
            'password.letters' => '비밀번호에 영문자가 1자 이상 포함되어야 합니다.',
            'password.numbers' => '비밀번호에 숫자가 1자 이상 포함되어야 합니다.',
        ], [
            'vendor_name' => '학원명', 'owner_name' => '원장명', 'phone' => '휴대폰',
        ]);

        $phone = preg_replace('/[^0-9]/', '', $data['phone']);
        $bizNo = ! empty($data['business_no']) ? preg_replace('/[^0-9]/', '', $data['business_no']) : null;

        $vendorId = null;
        $userId   = null;

        DB::transaction(function () use ($data, $phone, $bizNo, $agent, &$vendorId, &$userId) {
            $vendorId = DB::table('vendors')->insertGetId([
                'name'           => $data['vendor_name'],
                'owner_name'     => $data['owner_name'],
                'business_no'    => $bizNo,
                'mobile'         => $phone,
                'type_code'      => 'academy',
                'status_code'    => 'active',
                'trade_type'     => 'retail',      // 소매 기본 — 도매는 영업자가 학원 상세에서 변경
                'default_ship_to_type' => 'parent',
                'region_id'      => $data['region_id'] ?? null,
                'address'        => $data['address'] ?? null,
                'address_detail' => $data['address_detail'] ?? null,
                'payment_type'   => 'cash',
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            $user = User::create([
                'login_id'    => strtolower($data['login_id']),
                'email'       => $data['email'] ?? null,
                'name'        => $data['owner_name'],
                'phone'       => $phone,
                'password'    => $data['password'],   // 모델 캐스트로 해시
                'role_code'   => 'academy',
                'status_code' => 'active',            // 영업자 대행 등록과 동일 — 바로 사용 가능
                'region_id'   => $data['region_id'] ?? null,
                'address'     => $data['address'] ?? null,
                'address_detail' => $data['address_detail'] ?? null,
                'approved_by' => $agent->id,
                'approved_at' => now(),
            ]);
            $userId = $user->id;

            DB::table('vendor_users')->insert([
                'vendor_id'  => $vendorId,
                'user_id'    => $user->id,
                'role'       => 'owner',
                'is_primary' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('agent_vendor_discounts')->insert([
                'agent_user_id' => $agent->id,
                'vendor_id'     => $vendorId,
                'discount_rate' => self::DEFAULT_DISCOUNT_RATE,
                'is_active'     => true,
                'started_at'    => now()->toDateString(),
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        });

        AuditLog::log('vendors', $vendorId, 'academy_self_join', null, [
            'vendor_name'   => $data['vendor_name'],
            'agent_user_id' => $agent->id,
            'user_id'       => $userId,
        ]);

        return redirect()->route('academy.join.done')
            ->with('joined_login_id', strtolower($data['login_id']))
            ->with('joined_agent_name', $agent->name);
    }

    /** 가입 완료 안내 */
    public function done(Request $request)
    {
        $loginId = $request->session()->get('joined_login_id');
        if (! $loginId) {
            return redirect()->route('public.login');
        }
        return view('public.auth.academy_join_done', [
            'login_id'   => $loginId,
            'agent_name' => $request->session()->get('joined_agent_name'),
        ]);
    }
}
