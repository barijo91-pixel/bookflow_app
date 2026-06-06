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

        $sidos = DB::table('regions')->where('level', 'sido')->orderBy('sort_order')->get();

        // 학원 등록 시 할인율 디폴트 — 항상 10% (정책: 신규 학원 일괄 10% 시작)
        $defaultRate = 10;

        return view('public.mypage.vendor_create', compact('user', 'sidos', 'defaultRate'));
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

    /** 학원 상세 — 본인 담당 학원만 (admin show와 동일 구조 / 영업자 권한 내) */
    public function show($vendorId)
    {
        $user = $this->authorizeAgent();

        // 영업자 본인이 매핑된 학원만 접근 허용
        $myMapping = DB::table('agent_vendor_discounts')
            ->where('agent_user_id', $user->id)
            ->where('vendor_id', $vendorId)
            ->first();
        if (! $myMapping) {
            abort(404, '담당 학원이 아닙니다.');
        }

        $vendor = DB::table('vendors')->where('id', $vendorId)->whereNull('deleted_at')->first();
        if (! $vendor) abort(404);

        // 지역
        $sidos    = DB::table('regions')->where('level', 'sido')->orderBy('sort_order')->get();
        $sigungus = collect();
        $currentSidoId = null;
        if ($vendor->region_id) {
            $sigungu = DB::table('regions')->where('id', $vendor->region_id)->first();
            if ($sigungu) {
                $currentSidoId = $sigungu->parent_id;
                $sigungus = DB::table('regions')->where('parent_id', $sigungu->parent_id)->orderBy('sort_order')->get();
            }
        }

        // 본인이 한 최근 주문 (이 학원에 대한)
        $recentOrders = DB::table('orders as o')
            ->where('o.vendor_id', $vendorId)
            ->where('o.agent_user_id', $user->id)
            ->whereNull('o.deleted_at')
            ->orderByDesc('o.id')
            ->limit(10)
            ->select('o.id', 'o.order_no', 'o.status_code', 'o.total_amount', 'o.created_at')
            ->get();

        return view('public.mypage.vendor_show', compact(
            'user', 'vendor', 'sidos', 'sigungus', 'currentSidoId',
            'myMapping', 'recentOrders'
        ));
    }

    /** 학원 정보 수정 — 본인 담당 학원만 */
    public function update(Request $request, $vendorId)
    {
        $user = $this->authorizeAgent();

        // 권한 체크
        $hasMapping = DB::table('agent_vendor_discounts')
            ->where('agent_user_id', $user->id)
            ->where('vendor_id', $vendorId)
            ->exists();
        if (! $hasMapping) abort(403);

        $data = $request->validate([
            'name'           => ['required', 'string', 'max:150'],
            'owner_name'     => ['nullable', 'string', 'max:100'],
            'business_no'    => ['nullable', 'string', 'max:20'],
            'mobile'         => ['nullable', 'string', 'max:20'],
            'tel'            => ['nullable', 'string', 'max:20'],
            'region_id'      => ['nullable', 'integer', 'exists:regions,id'],
            'address'        => ['nullable', 'string', 'max:255'],
            'address_detail' => ['nullable', 'string', 'max:255'],
            'payment_type'   => ['nullable', 'in:cash,credit'],
            'credit_limit'   => ['nullable', 'integer', 'min:0', 'max:999999999'],
            'memo'           => ['nullable', 'string', 'max:2000'],
        ]);

        $mobile = ! empty($data['mobile']) ? preg_replace('/[^0-9]/', '', $data['mobile']) : null;
        $tel    = ! empty($data['tel'])    ? preg_replace('/[^0-9]/', '', $data['tel'])    : null;
        $paymentType = ($data['payment_type'] ?? 'cash') === 'credit' ? 'credit' : 'cash';
        $creditLimit = $paymentType === 'credit' ? (int) ($data['credit_limit'] ?? 0) : 0;

        DB::table('vendors')->where('id', $vendorId)->update([
            'name'           => $data['name'],
            'owner_name'     => $data['owner_name'] ?? null,
            'business_no'    => $data['business_no'] ?? null,
            'mobile'         => $mobile,
            'tel'            => $tel,
            'region_id'      => $data['region_id'] ?? null,
            'address'        => $data['address'] ?? null,
            'address_detail' => $data['address_detail'] ?? null,
            'payment_type'   => $paymentType,
            'credit_limit'   => $creditLimit,
            'memo'           => $data['memo'] ?? null,
            'updated_at'     => now(),
        ]);

        AuditLog::log('vendors', $vendorId, 'agent_update', null, [
            'agent_user_id' => $user->id,
            'vendor_name'   => $data['name'],
        ]);

        return redirect()->route('my.vendors.show', $vendorId)->with('success', '학원 정보가 저장되었습니다.');
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
