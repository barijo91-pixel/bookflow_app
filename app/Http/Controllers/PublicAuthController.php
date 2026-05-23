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
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);
        $remember = (bool) $request->boolean('remember', true);

        // === Rate Limit: 5회 실패 → 60초 잠금 (IP + 이메일 기준) ===
        $rlKey = 'login:'.Str::lower($data['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($rlKey, 5)) {
            $seconds = RateLimiter::availableIn($rlKey);
            return back()->withInput($request->only('email'))->withErrors([
                'email' => "로그인 시도가 너무 많습니다. {$seconds}초 후 다시 시도해주세요.",
            ]);
        }

        if (! Auth::attempt($data, $remember)) {
            RateLimiter::hit($rlKey, 60);
            return back()->withInput($request->only('email'))->withErrors([
                'email' => '이메일 또는 비밀번호가 올바르지 않습니다.',
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
            return back()->withInput($request->only('email'))->withErrors(['email' => $msg]);
        }

        RateLimiter::clear($rlKey);

        // 이전 로그인 시각 세션에 저장 (마이페이지/대시보드에 표시)
        $previousLoginAt = $user->last_login_at;
        $user->forceFill(['last_login_at' => now()])->save();

        $request->session()->regenerate();
        $request->session()->put('previous_login_at', $previousLoginAt?->toIso8601String());
        $request->session()->put('current_login_ip', $request->ip());
        return $this->redirectAfterLogin($user);
    }

    public function showRegister()
    {
        if (Auth::check()) {
            return $this->redirectAfterLogin(Auth::user());
        }
        $distributors = User::where('role_code', 'distributor')
            ->where('status_code', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);
        return view('public.auth.register', compact('distributors'));
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'email'    => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers(), 'max:50'],
            'name'     => ['required', 'string', 'max:100'],
            'phone'    => ['required', 'string', 'max:20'],
            'role_code'=> ['required', Rule::in(['distributor', 'agent', 'academy'])],
            'parent_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'agree_terms'    => ['accepted'],
        ], [
            'password.min'     => '비밀번호는 최소 8자 이상이어야 합니다.',
            'password.letters' => '비밀번호에 영문자가 1자 이상 포함되어야 합니다.',
            'password.numbers' => '비밀번호에 숫자가 1자 이상 포함되어야 합니다.',
        ]);
        $phone = preg_replace('/[^0-9]/', '', $data['phone']);

        $user = User::create([
            'email'    => $data['email'],
            'password' => $data['password'],
            'name'     => $data['name'],
            'phone'    => $phone,
            'role_code'=> $data['role_code'],
            'status_code' => 'pending',
        ]);

        // 영업자가 총판 선택해서 가입한 경우 user_relations 등록
        if ($data['role_code'] === 'agent' && ! empty($data['parent_user_id'])) {
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

        AuditLog::log('users', $user->id, 'self_register', null, [
            'email' => $user->email, 'role_code' => $user->role_code,
        ]);

        return redirect()->route('public.register.done')->with('registered_email', $user->email);
    }

    public function registerDone(Request $request)
    {
        $email = $request->session()->get('registered_email');
        if (! $email) {
            return redirect()->route('public.login');
        }
        return view('public.auth.register_done', compact('email'));
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
        // 관리자는 admin 대시보드, 나머지는 마이페이지
        if ($user->role_code === 'admin') {
            return redirect()->intended(route('admin.dashboard'));
        }
        return redirect()->intended(route('mypage'));
    }
}
