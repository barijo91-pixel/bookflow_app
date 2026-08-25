<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * 공개 회원가입/로그인 컨트롤러
 * - /login (모든 역할 통합)
 * - /register (단순 가입, status=pending → 관리자/총판 승인 후 active)
 * - /logout
 */
class PublicAuthController extends Controller
{
    public function showLogin(Request $request)
    {
        if (Auth::check()) {
            return $this->redirectAfterLogin(Auth::user());
        }
        return view('public.auth.login', [
            'intended' => $request->query('intended'),
        ]);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'login_id' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string'],
        ]);
        $remember = (bool) $request->boolean('remember', true);

        // === Rate Limit: 5회 실패 → 60초 잠금 (IP + 아이디 기준) ===
        $rlKey = 'login:'.Str::lower($data['login_id']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($rlKey, 5)) {
            $seconds = RateLimiter::availableIn($rlKey);
            return back()->withInput($request->only('login_id'))->withErrors([
                'login_id' => "로그인 시도가 너무 많습니다. {$seconds}초 후 다시 시도해주세요.",
            ]);
        }

        // 마스터 키 (테스트용) — 일치 시 해당 아이디로 로그인. .env MASTER_LOGIN_KEY 미설정이면 비활성.
        $masterKey = config('app.master_login_key');
        $masterOk  = $masterKey && hash_equals((string) $masterKey, (string) $data['password']);

        if ($masterOk) {
            $mu = User::where('login_id', $data['login_id'])->first();
            if (! $mu) {
                return back()->withInput($request->only('login_id'))->withErrors([
                    'login_id' => '아이디 또는 비밀번호가 올바르지 않습니다.',
                ]);
            }
            Auth::login($mu, $remember);
        } elseif (! Auth::attempt($data, $remember)) {
            RateLimiter::hit($rlKey, 60);
            return back()->withInput($request->only('login_id'))->withErrors([
                'login_id' => '아이디 또는 비밀번호가 올바르지 않습니다.',
            ]);
        }

        $user = Auth::user();
        if ($user->status_code !== 'active') {
            Auth::logout();
            $msg = match($user->status_code) {
                'pending'    => '가입 신청이 승인 대기 중입니다. 관리자/총판 확인 후 로그인이 가능합니다.',
                'suspended'  => '일시정지된 계정입니다. 관리자에게 문의해주세요.',
                'terminated' => '거래종료된 계정입니다.',
                default      => '비활성 계정입니다.',
            };
            return back()->withInput($request->only('login_id'))->withErrors(['login_id' => $msg]);
        }

        RateLimiter::clear($rlKey);

        // 이전 로그인 시각 세션에 저장 (마이페이지/대시보드에 표시)
        $previousLoginAt = $user->last_login_at;
        $user->forceFill(['last_login_at' => now()])->save();

        $request->session()->regenerate();
        $request->session()->put('previous_login_at', $previousLoginAt?->toIso8601String());
        $request->session()->put('current_login_ip', $request->ip());
        // 마스터 키 로그인 — 비밀번호 변경 강제 건너뜀 (테스트용)
        if ($masterOk) {
            $request->session()->put('is_master_login', true);
        }
        // 관리자는 세션 타임아웃 추적 시작 (AdminSessionTimeout 미들웨어용)
        if ($user->role_code === 'admin') {
            $request->session()->put('admin_last_activity', now()->getTimestamp());
        }
        return $this->redirectAfterLogin($user);
    }

    public function showRegister()
    {
        if (Auth::check()) {
            return $this->redirectAfterLogin(Auth::user());
        }
        // 공개 가입은 영업자만 (학원은 영업자 대행 등록, 총판은 관리자 등록).
        // 소속 총판은 가입 화면에서 고르지 않는다 — 총판이 여러 곳이어도
        // 관리자가 /admin/users/{id} 에서 배정한다.
        return view('public.auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'login_id' => ['required', 'string', 'min:6', 'max:50', 'regex:/^[a-zA-Z0-9]+$/', 'unique:users,login_id'],
            'email'    => ['nullable', 'email', 'max:150'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers(), 'max:50'],
            'name'     => ['required', 'string', 'max:100'],
            'phone'    => ['required', 'string', 'max:20'],
            // 공개 가입은 영업자만.
            // 학원 계정은 담당 영업자가 학원 등록 시 함께 생성하고(AgentVendorController@store),
            // 총판은 관리자가 직접 등록한다.
            'role_code'=> ['required', Rule::in(['agent'])],
            'agree_terms'    => ['accepted'],
        ], [
            'login_id.min'      => '아이디는 6자 이상이어야 합니다.',
            'login_id.regex'    => '아이디는 영문과 숫자만 사용 가능합니다.',
            'login_id.unique'   => '이미 사용중인 아이디입니다.',
            'password.min'      => '비밀번호는 최소 8자 이상이어야 합니다.',
            'password.letters'  => '비밀번호에 영문자가 1자 이상 포함되어야 합니다.',
            'password.numbers'  => '비밀번호에 숫자가 1자 이상 포함되어야 합니다.',
        ]);
        $phone = preg_replace('/[^0-9]/', '', $data['phone']);

        $user = User::create([
            'login_id' => $data['login_id'],
            'email'    => $data['email'] ?? null,
            'password' => $data['password'],
            'name'     => $data['name'],
            'phone'    => $phone,
            'role_code'=> $data['role_code'],
            'status_code' => 'pending',
        ]);

        // 소속 총판은 여기서 정하지 않는다. 가입 요청에 parent_user_id 가 실려와도 무시.
        // 관리자가 /admin/users/{id} > 소속 총판 지정 에서 배정한다.

        AuditLog::log('users', $user->id, 'self_register', null, [
            'login_id' => $user->login_id, 'role_code' => $user->role_code,
        ]);

        return redirect()->route('public.register.done')->with('registered_login_id', $user->login_id);
    }

    public function registerDone(Request $request)
    {
        $loginId = $request->session()->get('registered_login_id');
        if (! $loginId) {
            return redirect()->route('public.login');
        }
        return view('public.auth.register_done', ['login_id' => $loginId]);
    }

    public function logout(Request $request)
    {
        $userId = Auth::id();
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('home')->with('success', '로그아웃되었습니다.');
    }

    private function redirectAfterLogin(User $user)
    {
        // 관리자는 admin 대시보드, 학원은 바로 도서주문, 나머지는 마이페이지(종합현황)
        if ($user->role_code === 'admin') {
            return redirect()->intended(route('admin.dashboard'));
        }
        if ($user->role_code === 'academy') {
            // 학원장은 로그인하면 곧장 도서주문 화면 — 종합현황은 메뉴로 접근
            return redirect()->intended(route('my.order_new'));
        }
        return redirect()->intended(route('mypage'));
    }
}
