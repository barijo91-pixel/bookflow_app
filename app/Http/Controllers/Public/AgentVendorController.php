<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AgentVendorController extends Controller
{
    /** 영업자만 접근 */
    private function authorizeAgent(): User
    {
        $user = Auth::user();
        if (! $user || $user->role_code !== 'agent') {
            abort(403, '영업자만 접근 가능합니다.');
        }
        return $user;
    }

    /** 새 학원 등록 폼 */
    public function create()
    {
        $user = $this->authorizeAgent();

        $bankOptions = DB::table('codes')->where('group_code', 'bank')->orderBy('sort_order')->get();
        $sidos       = DB::table('regions')->where('level', 'sido')->orderBy('sort_order')->get();

        // 영업자의 기본 할인율 (참고용 디폴트) — 본인 첫 매핑의 rate
        $defaultRate = DB::table('agent_vendor_discounts')
            ->where('agent_user_id', $user->id)
            ->where('is_active', true)
            ->orderBy('id')
            ->value('discount_rate') ?? 30;

        return view('public.mypage.vendor_create', compact('user', 'bankOptions', 'sidos', 'defaultRate'));
    }

    /** 학원 + 학원 계정 + 매핑 동시 생성 */
    public function store(Request $request)
    {
        $user = $this->authorizeAgent();

        $data = $request->validate([
            // 학원 정보
            'vendor_name'    => ['required', 'string', 'max:150'],
            'owner_name'     => ['nullable', 'string', 'max:100'],
            'business_no'    => ['nullable', 'string', 'max:20'],
            'vendor_mobile'  => ['nullable', 'string', 'max:20'],
            'vendor_tel'     => ['nullable', 'string', 'max:20'],
            'region_id'      => ['nullable', 'integer', 'exists:regions,id'],
            'address'        => ['nullable', 'string', 'max:255'],
            'address_detail' => ['nullable', 'string', 'max:255'],
            'bank_code'      => ['nullable', 'string', 'max:10'],
            'bank_account'   => ['nullable', 'string', 'max:50'],
            'bank_holder'    => ['nullable', 'string', 'max:50'],
            'payment_type'   => ['nullable', 'in:cash,credit'],
            'credit_limit'   => ['nullable', 'integer', 'min:0', 'max:999999999'],
            'memo'           => ['nullable', 'string', 'max:2000'],
            // 학원 계정 (선택 — 비워두면 계정 없이 거래처만 등록)
            'create_account'    => ['nullable', 'in:0,1'],
            'user_login_id'     => ['nullable', 'string', 'regex:/^[a-zA-Z0-9]{6,50}$/'],
            'user_name'         => ['nullable', 'string', 'max:80'],
            'user_phone'        => ['nullable', 'string', 'max:20'],
            'user_email'        => ['nullable', 'email', 'max:150'],
            'user_password'     => ['nullable', 'string', 'min:8', 'max:50'],
            // 영업자 할인율 (본인 매핑)
            'discount_rate'  => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $createAccount = ($data['create_account'] ?? '0') === '1';

        // 계정 생성 시 추가 필수값 검증
        if ($createAccount) {
            $req = ['user_login_id', 'user_name', 'user_phone'];
            $missing = [];
            foreach ($req as $f) {
                if (empty($data[$f])) $missing[] = $f;
            }
            if ($missing) {
                return back()->withInput()->with('error',
                    '학원 계정을 만들려면 아이디·이름·휴대폰이 필요합니다.');
            }
            // login_id 중복 체크
            $exists = DB::table('users')->where('login_id', strtolower($data['user_login_id']))->exists();
            if ($exists) {
                return back()->withInput()->withErrors([
                    'user_login_id' => "아이디 '{$data['user_login_id']}'는 이미 사용 중입니다.",
                ]);
            }
        }

        $vendorId = null;
        $createdUser = null;
        $plainPw = null;

        DB::transaction(function () use ($data, $user, $createAccount, &$vendorId, &$createdUser, &$plainPw) {
            $phone = ! empty($data['vendor_mobile']) ? preg_replace('/[^0-9]/', '', $data['vendor_mobile']) : null;
            $tel   = ! empty($data['vendor_tel'])    ? preg_replace('/[^0-9]/', '', $data['vendor_tel'])    : null;

            $paymentType = ($data['payment_type'] ?? 'cash') === 'credit' ? 'credit' : 'cash';
            $creditLimit = $paymentType === 'credit' ? (int) ($data['credit_limit'] ?? 0) : 0;

            $vendorId = DB::table('vendors')->insertGetId([
                'name'           => $data['vendor_name'],
                'owner_name'     => $data['owner_name'] ?? null,
                'business_no'    => $data['business_no'] ?? null,
                'type_code'      => 'academy',
                'status_code'    => 'active',
                'mobile'         => $phone,
                'tel'            => $tel,
                'region_id'      => $data['region_id'] ?? null,
                'address'        => $data['address'] ?? null,
                'address_detail' => $data['address_detail'] ?? null,
                'bank_code'      => $data['bank_code'] ?? null,
                'bank_account'   => $data['bank_account'] ?? null,
                'bank_holder'    => $data['bank_holder'] ?? null,
                'payment_type'   => $paymentType,
                'credit_limit'   => $creditLimit,
                'memo'           => $data['memo'] ?? null,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            // 학원 계정 동시 생성
            if ($createAccount) {
                $plainPw = ! empty($data['user_password']) ? $data['user_password'] : $this->genPassword(8);
                $userPhone = preg_replace('/[^0-9]/', '', (string) ($data['user_phone'] ?? ''));

                $createdUser = User::create([
                    'login_id'    => strtolower($data['user_login_id']),
                    'email'       => $data['user_email'] ?? null,
                    'name'        => $data['user_name'],
                    'phone'       => $userPhone,
                    'password'    => $plainPw, // 모델 캐스트로 해시
                    'password_change_required' => true,
                    'role_code'   => 'academy',
                    'status_code' => 'active',
                    'region_id'   => $data['region_id'] ?? null,
                    'address'     => $data['address'] ?? null,
                    'address_detail' => $data['address_detail'] ?? null,
                    'approved_by' => $user->id,
                    'approved_at' => now(),
                ]);

                // vendor ↔ user 매핑
                DB::table('vendor_users')->insert([
                    'vendor_id'  => $vendorId,
                    'user_id'    => $createdUser->id,
                    'role'       => 'owner',
                    'is_primary' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // 영업자(본인) ↔ vendor 할인율 매핑
            DB::table('agent_vendor_discounts')->insert([
                'agent_user_id' => $user->id,
                'vendor_id'     => $vendorId,
                'discount_rate' => $data['discount_rate'],
                'is_active'     => true,
                'started_at'    => now()->toDateString(),
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        });

        AuditLog::log('vendors', $vendorId, 'agent_create', null, [
            'vendor_name'   => $data['vendor_name'],
            'agent_user_id' => $user->id,
            'discount_rate' => $data['discount_rate'],
            'account_created' => $createAccount,
        ]);

        // 계정도 만들었으면 초기 비번 1회 표시
        if ($createdUser && $plainPw) {
            return view('public.mypage.vendor_created', [
                'user'        => $user,
                'vendorId'    => $vendorId,
                'vendorName'  => $data['vendor_name'],
                'createdUser' => [
                    'login_id' => $createdUser->login_id,
                    'name'     => $createdUser->name,
                    'phone'    => $createdUser->phone,
                    'password' => $plainPw,
                ],
            ]);
        }

        return redirect()->route('my.vendors.index')->with('success',
            "학원 「{$data['vendor_name']}」이(가) 등록되었습니다.");
    }

    /** 자동 비번 생성 (혼동 문자 제외) */
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
