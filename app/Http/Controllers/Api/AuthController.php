<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    /** 휴대폰 인증번호 발송 */
    public function sendPhoneCode(Request $request, NotificationService $notify)
    {
        $data = $request->validate([
            'phone'   => ['required', 'string', 'min:10', 'max:20'],
            'purpose' => ['nullable', 'string', 'max:30'],
        ]);
        $phone = preg_replace('/[^0-9]/', '', $data['phone']);
        $purpose = $data['purpose'] ?? 'signup';

        // 일일 발송 한도 체크
        $limit = (int) (setting('phone_verify_resend_limit', '5'));
        $today = DB::table('phone_verifications')
            ->where('phone', $phone)
            ->whereDate('created_at', today())
            ->count();
        if ($today >= $limit) {
            return response()->json(['ok' => false, 'error' => "오늘 발송 한도({$limit}회)를 초과했습니다."], 429);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $ttl  = (int) (setting('phone_verify_ttl', '300'));

        DB::table('phone_verifications')->insert([
            'phone'      => $phone,
            'code'       => $code,
            'purpose'    => $purpose,
            'expires_at' => now()->addSeconds($ttl),
            'attempts'   => 0,
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $notify->sendPhoneVerification($phone, $code);

        return response()->json([
            'ok' => true,
            'message' => '인증번호가 발송되었습니다.',
            'ttl_seconds' => $ttl,
            // dev 환경에서는 코드 노출 (테스트 편의)
            'dev_code' => app()->environment('local') ? $code : null,
        ]);
    }

    /** 인증번호 확인 */
    public function verifyPhoneCode(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'string'],
            'code'  => ['required', 'string', 'size:6'],
        ]);
        $phone = preg_replace('/[^0-9]/', '', $data['phone']);

        $row = DB::table('phone_verifications')
            ->where('phone', $phone)
            ->whereNull('verified_at')
            ->orderByDesc('id')
            ->first();
        if (! $row) {
            return response()->json(['ok' => false, 'error' => '인증 요청 이력이 없습니다.'], 400);
        }
        if (now()->greaterThan($row->expires_at)) {
            return response()->json(['ok' => false, 'error' => '인증번호가 만료되었습니다.'], 400);
        }
        if ($row->attempts >= 5) {
            return response()->json(['ok' => false, 'error' => '시도 횟수 초과. 새로 발송하세요.'], 429);
        }

        DB::table('phone_verifications')->where('id', $row->id)->update([
            'attempts'   => $row->attempts + 1,
            'updated_at' => now(),
        ]);

        if ($row->code !== $data['code']) {
            return response()->json(['ok' => false, 'error' => '인증번호가 일치하지 않습니다.'], 400);
        }

        DB::table('phone_verifications')->where('id', $row->id)->update([
            'verified_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'verification_token' => hash('sha256', $row->id.'|'.$phone.'|'.config('app.key')),
        ]);
    }

    /** 회원가입 (휴대폰 인증 완료 후) */
    public function register(Request $request)
    {
        $data = $request->validate([
            'login_id' => ['required', 'string', 'min:6', 'max:50', 'regex:/^[a-zA-Z0-9]+$/', 'unique:users,login_id'],
            'email'    => ['nullable', 'email', 'max:150'],
            'password' => ['required', 'string', 'min:8', 'max:50', 'regex:/^(?=.*[A-Za-z])(?=.*\d).+$/'],
            'name'     => ['required', 'string', 'max:100'],
            'phone'    => ['required', 'string'],
            'role_code'=> ['required', Rule::in(['distributor','agent','academy'])],
            'parent_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'verification_token' => ['required', 'string'],
        ]);
        $phone = preg_replace('/[^0-9]/', '', $data['phone']);

        // 휴대폰 인증 확인
        $verification = DB::table('phone_verifications')
            ->where('phone', $phone)
            ->whereNotNull('verified_at')
            ->orderByDesc('id')->first();
        if (! $verification) {
            return response()->json(['ok' => false, 'error' => '휴대폰 인증이 필요합니다.'], 400);
        }
        $expectedToken = hash('sha256', $verification->id.'|'.$phone.'|'.config('app.key'));
        if (! hash_equals($expectedToken, $data['verification_token'])) {
            return response()->json(['ok' => false, 'error' => '인증 토큰이 유효하지 않습니다.'], 400);
        }

        $user = User::create([
            'login_id' => $data['login_id'],
            'email'    => $data['email'] ?? null,
            'password' => $data['password'],
            'name'     => $data['name'],
            'phone'    => $phone,
            'phone_verified_at' => now(),
            'role_code' => $data['role_code'],
            'status_code' => 'pending', // 가입 시 대기 (관리자/총판 승인 후 active)
        ]);

        // 영업자가 총판 선택해서 가입한 경우 user_relations 등록 (대기 상태)
        if (! empty($data['parent_user_id']) && $data['role_code'] === 'agent') {
            $parent = User::find($data['parent_user_id']);
            if ($parent && $parent->role_code === 'distributor') {
                DB::table('user_relations')->insert([
                    'parent_user_id' => $parent->id,
                    'child_user_id'  => $user->id,
                    'relation_type'  => 'distributor_agent',
                    'status'         => 'active',
                    'started_at'     => now()->toDateString(),
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
            }
        }

        return response()->json([
            'ok' => true,
            'user_id' => $user->id,
            'message' => '회원가입이 완료되었습니다. 관리자/총판 승인 후 로그인 가능합니다.',
        ]);
    }

    /** 로그인 → Sanctum 토큰 발급 */
    public function login(Request $request)
    {
        $data = $request->validate([
            'login_id' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::where('login_id', $data['login_id'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['ok' => false, 'error' => '아이디 또는 비밀번호가 올바르지 않습니다.'], 401);
        }
        if ($user->status_code !== 'active') {
            $statusMsg = match($user->status_code) {
                'pending' => '가입 승인 대기 중입니다.',
                'suspended' => '일시정지된 계정입니다.',
                'terminated' => '거래종료된 계정입니다.',
                default => '비활성 계정입니다.',
            };
            return response()->json(['ok' => false, 'error' => $statusMsg], 403);
        }

        $user->forceFill(['last_login_at' => now()])->save();
        $token = $user->createToken($data['device_name'] ?? 'mobile')->plainTextToken;

        return response()->json([
            'ok' => true,
            'token' => $token,
            'user' => [
                'id'        => $user->id,
                'login_id'  => $user->login_id,
                'email'     => $user->email,
                'name'      => $user->name,
                'phone'     => $user->phone,
                'role_code' => $user->role_code,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['ok' => true]);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'ok' => true,
            'user' => $user->only(['id','login_id','email','name','phone','role_code','status_code']),
        ]);
    }
}
