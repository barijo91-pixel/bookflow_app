<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    // -------------------- LIST --------------------
    public function index(Request $request)
    {
        $role        = $request->query('role');
        $status      = $request->query('status');
        $q           = trim((string) $request->query('q'));
        $distributor = (int) $request->query('distributor');

        // 정렬
        $allowedSorts = ['id', 'name', 'login_id', 'phone', 'role_code', 'status_code', 'created_at'];
        $sort = $request->query('sort', 'id');
        $dir  = $request->query('dir', 'desc');
        if (! in_array($sort, $allowedSorts, true)) $sort = 'id';
        if (! in_array($dir, ['asc', 'desc'], true)) $dir = 'desc';

        $query = User::query()->orderBy($sort, $dir);
        if ($sort !== 'id') $query->orderByDesc('id');

        if ($role)   { $query->where('role_code', $role); }
        if ($status) { $query->where('status_code', $status); }
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                  ->orWhere('login_id', 'like', "%{$q}%")
                  ->orWhere('email', 'like', "%{$q}%")
                  ->orWhere('phone', 'like', "%{$q}%");
            });
        }
        // 총판 필터: 그 총판 본인 + 산하 영업자 + 영업자가 담당하는 학원 사용자
        if ($distributor) {
            // 산하 영업자 ID
            $agentIds = DB::table('user_relations')
                ->where('parent_user_id', $distributor)
                ->where('relation_type', 'distributor_agent')
                ->where('status', 'active')
                ->pluck('child_user_id')->toArray();
            // 그 영업자들이 담당하는 vendor ID
            $vendorIds = DB::table('agent_vendor_discounts')
                ->whereIn('agent_user_id', $agentIds)
                ->where('is_active', true)
                ->pluck('vendor_id')->unique()->toArray();
            // 그 vendor의 학원 사용자 ID
            $academyIds = DB::table('vendor_users')
                ->whereIn('vendor_id', $vendorIds)
                ->pluck('user_id')->unique()->toArray();

            $includeIds = array_unique(array_merge([$distributor], $agentIds, $academyIds));
            $query->whereIn('id', $includeIds);
        }

        $users = $query->paginate(20)->withQueryString();
        $roleOptions   = DB::table('codes')->where('group_code', 'user_role')->orderBy('sort_order')->get();
        $statusOptions = DB::table('codes')->where('group_code', 'user_status')->orderBy('sort_order')->get();

        // 총판 셀렉트 옵션
        $distributorOptions = User::where('role_code', 'distributor')
            ->orderBy('name')->get(['id', 'name', 'login_id']);

        // 소속 정보 일괄 조회 (N+1 방지)
        $userIds = $users->pluck('id')->toArray();
        $affiliations = $this->loadAffiliations($userIds);

        return view('admin.users.index', compact(
            'users', 'roleOptions', 'statusOptions', 'role', 'status', 'q',
            'affiliations', 'distributorOptions', 'distributor', 'sort', 'dir'
        ));
    }

    /**
     * 사용자 ID 배열에 대한 소속 정보 일괄 조회
     * @return array<int, array{names: array, count: int}>  [user_id => ['names' => [...], 'count' => N]]
     */
    private function loadAffiliations(array $userIds): array
    {
        if (empty($userIds)) return [];

        $result = [];

        // 영업자 → 속한 총판들
        $agentToDistributors = DB::table('user_relations as r')
            ->join('users as u', 'u.id', '=', 'r.parent_user_id')
            ->whereIn('r.child_user_id', $userIds)
            ->where('r.relation_type', 'distributor_agent')
            ->where('r.status', 'active')
            ->select('r.child_user_id as user_id', 'u.name as parent_name')
            ->get()
            ->groupBy('user_id');

        foreach ($agentToDistributors as $userId => $rows) {
            $result[$userId] = [
                'names' => $rows->pluck('parent_name')->all(),
                'count' => $rows->count(),
            ];
        }

        // 학원 → 속한 vendor(거래처) 명
        $academyToVendors = DB::table('vendor_users as vu')
            ->join('vendors as v', 'v.id', '=', 'vu.vendor_id')
            ->whereIn('vu.user_id', $userIds)
            ->whereNull('v.deleted_at')
            ->select('vu.user_id', 'v.name as vendor_name')
            ->get()
            ->groupBy('user_id');

        foreach ($academyToVendors as $userId => $rows) {
            $result[$userId] = [
                'names' => $rows->pluck('vendor_name')->all(),
                'count' => $rows->count(),
            ];
        }

        // 총판 → 산하 영업자 수 (이름은 너무 길어질 수 있으니 카운트만)
        $distributorAgentCount = DB::table('user_relations')
            ->whereIn('parent_user_id', $userIds)
            ->where('relation_type', 'distributor_agent')
            ->where('status', 'active')
            ->select('parent_user_id', DB::raw('count(*) as cnt'))
            ->groupBy('parent_user_id')->get();

        foreach ($distributorAgentCount as $row) {
            $result[$row->parent_user_id] = [
                'names' => [], // 카운트만
                'count' => $row->cnt,
                'is_distributor' => true,
            ];
        }

        return $result;
    }

    public function pending()
    {
        $users = User::where('status_code', 'pending')->orderBy('id')->paginate(20);
        return view('admin.users.pending', compact('users'));
    }

    // -------------------- CREATE --------------------
    public function create()
    {
        $roleOptions = DB::table('codes')->where('group_code', 'user_role')->orderBy('sort_order')->get();
        $sidos       = DB::table('regions')->where('level', 'sido')->orderBy('sort_order')->get();
        return view('admin.users.create', compact('roleOptions', 'sidos'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'login_id'      => ['required', 'string', 'min:6', 'max:50', 'regex:/^[a-zA-Z0-9]+$/', 'unique:users,login_id'],
            'email'         => ['nullable', 'email', 'max:150'],
            'name'          => ['required', 'string', 'max:100'],
            'phone'         => ['required', 'string', 'max:20'],
            'password'      => ['required', Password::min(8)->letters()->numbers(), 'max:50'],
            'role_code'     => ['required', Rule::in(['admin','distributor','agent','academy'])],
            'admin_level'   => ['nullable', Rule::in(['super','staff'])],
            'region_id'     => ['nullable', 'integer', 'exists:regions,id'],
            'address'       => ['nullable', 'string', 'max:255'],
            'address_detail'=> ['nullable', 'string', 'max:255'],
        ], [
            'login_id.min'     => '아이디는 6자 이상이어야 합니다.',
            'login_id.regex'   => '아이디는 영문과 숫자만 사용 가능합니다.',
            'login_id.unique'  => '이미 사용중인 아이디입니다.',
            'password.min'     => '비밀번호는 최소 8자 이상이어야 합니다.',
            'password.letters' => '비밀번호에 영문자가 1자 이상 포함되어야 합니다.',
            'password.numbers' => '비밀번호에 숫자가 1자 이상 포함되어야 합니다.',
        ]);

        $user = new User();
        $user->login_id = $data['login_id'];
        $user->email = $data['email'] ?? null;
        $user->password = $data['password']; // model casts to hashed
        $user->password_change_required = true; // 관리자 생성 계정은 첫 로그인 시 비번 변경 강제
        $user->name = $data['name'];
        $user->phone = $data['phone'];
        $user->role_code = $data['role_code'];
        $user->admin_level = $data['role_code'] === 'admin' ? ($data['admin_level'] ?? 'staff') : null;
        $user->region_id = $data['region_id'] ?? null;
        $user->address = $data['address'] ?? null;
        $user->address_detail = $data['address_detail'] ?? null;
        $user->status_code = 'active';
        $user->approved_by = auth()->id();
        $user->approved_at = now();
        $user->save();

        AuditLog::log('users', $user->id, 'create', null, $user->only(['login_id','email','name','phone','role_code','admin_level','status_code']));

        return redirect()->route('admin.users.show', $user)->with('success', '사용자가 등록되었습니다.');
    }

    // -------------------- SHOW (상세 + 편집 통합) --------------------
    public function show(User $user)
    {
        $roleOptions   = DB::table('codes')->where('group_code', 'user_role')->orderBy('sort_order')->get();
        $statusOptions = DB::table('codes')->where('group_code', 'user_status')->orderBy('sort_order')->get();
        $sidos         = DB::table('regions')->where('level', 'sido')->orderBy('sort_order')->get();

        // 현재 region의 부모(sido) 찾기
        $currentSidoId = null;
        $sigungus = collect();
        if ($user->region_id) {
            $reg = DB::table('regions')->find($user->region_id);
            if ($reg) {
                $currentSidoId = $reg->parent_id ?? $reg->id;
                $sigungus = DB::table('regions')->where('parent_id', $currentSidoId)->orderBy('sort_order')->get();
            }
        }

        // 관계 정보
        $relationsAsParent = DB::table('user_relations as r')
            ->join('users as u', 'u.id', '=', 'r.child_user_id')
            ->where('r.parent_user_id', $user->id)
            ->select('r.id','r.relation_type','r.status','r.started_at','r.terminated_at',
                'u.id as user_id','u.name as user_name','u.email as user_email','u.role_code')
            ->orderByDesc('r.id')->get();
        $relationsAsChild = DB::table('user_relations as r')
            ->join('users as u', 'u.id', '=', 'r.parent_user_id')
            ->where('r.child_user_id', $user->id)
            ->select('r.id','r.relation_type','r.status','r.started_at','r.terminated_at',
                'u.id as user_id','u.name as user_name','u.email as user_email','u.role_code')
            ->orderByDesc('r.id')->get();

        // 최근 주문 (역할별)
        $recentOrders = collect();
        if ($user->isAgent()) {
            $recentOrders = DB::table('orders')
                ->where('agent_user_id', $user->id)
                ->orderByDesc('id')->limit(10)->get();
        } elseif ($user->isDistributor()) {
            $recentOrders = DB::table('orders')
                ->where('distributor_user_id', $user->id)
                ->orderByDesc('id')->limit(10)->get();
        } elseif ($user->isAcademy()) {
            // vendor_users 통해 vendor 찾고 그 vendor의 주문
            $vendorIds = DB::table('vendor_users')->where('user_id', $user->id)->pluck('vendor_id');
            if ($vendorIds->isNotEmpty()) {
                $recentOrders = DB::table('orders')
                    ->whereIn('vendor_id', $vendorIds)
                    ->orderByDesc('id')->limit(10)->get();
            }
        }

        return view('admin.users.show', compact(
            'user', 'roleOptions', 'statusOptions', 'sidos', 'currentSidoId', 'sigungus',
            'relationsAsParent', 'relationsAsChild', 'recentOrders'
        ));
    }

    // -------------------- UPDATE --------------------
    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:100'],
            'phone'         => ['required', 'string', 'max:20'],
            'role_code'     => ['required', Rule::in(['admin','distributor','agent','academy'])],
            'admin_level'   => ['nullable', Rule::in(['super','staff'])],
            'status_code'   => ['required', Rule::in(['pending','active','suspended','terminated'])],
            'region_id'     => ['nullable', 'integer', 'exists:regions,id'],
            'address'       => ['nullable', 'string', 'max:255'],
            'address_detail'=> ['nullable', 'string', 'max:255'],
            // 사업자/정산계좌 (총판·사입자)
            'business_type' => ['nullable', Rule::in(['none','individual_simple','individual_general','corporate'])],
            'business_no'   => ['nullable', 'string', 'max:20'],
            'business_name' => ['nullable', 'string', 'max:150'],
            'bank_code'     => ['nullable', 'string', 'max:10'],
            'bank_account'  => ['nullable', 'string', 'max:40'],
            'bank_holder'   => ['nullable', 'string', 'max:50'],
        ]);

        $me = auth()->user();

        // GUARD: 본인 status 변경 차단 (자기 차단 방지)
        if ($user->id === $me->id && $data['status_code'] !== 'active') {
            return back()->withErrors(['status_code' => '본인 계정의 상태는 active 외로 변경할 수 없습니다.']);
        }

        // GUARD: 슈퍼관리자가 아닌 사람이 admin 권한 부여/박탈 차단
        if (! $me->isSuperAdmin()) {
            if ($data['role_code'] === 'admin' && $user->role_code !== 'admin') {
                return back()->withErrors(['role_code' => '슈퍼관리자만 admin 권한을 부여할 수 있습니다.']);
            }
            if ($user->role_code === 'admin' && $data['role_code'] !== 'admin') {
                return back()->withErrors(['role_code' => '슈퍼관리자만 관리자 권한을 박탈할 수 있습니다.']);
            }
            if ($user->role_code === 'admin' && $user->admin_level === 'super') {
                return back()->withErrors(['role_code' => '슈퍼관리자 계정은 슈퍼관리자만 수정할 수 있습니다.']);
            }
        }

        $user->name = $data['name'];
        $user->phone = $data['phone'];
        $user->role_code = $data['role_code'];
        $user->admin_level = $data['role_code'] === 'admin' ? ($data['admin_level'] ?? 'staff') : null;
        $user->status_code = $data['status_code'];
        $user->region_id = $data['region_id'] ?? null;
        $user->address = $data['address'] ?? null;
        $user->address_detail = $data['address_detail'] ?? null;

        // 사업자/정산계좌 — 총판/사입자 권한일 때만 저장
        if (in_array($data['role_code'], ['distributor', 'agent'])) {
            $user->business_type = $data['business_type'] ?? 'none';
            $user->business_no   = $data['business_no']   ?? null;
            $user->business_name = $data['business_name'] ?? null;
            $user->bank_code     = $data['bank_code']     ?? null;
            $user->bank_account  = $data['bank_account']  ?? null;
            $user->bank_holder   = $data['bank_holder']   ?? null;
        }

        // active로 바뀐 경우 approved_at 기록
        if ($data['status_code'] === 'active' && ! $user->approved_at) {
            $user->approved_by = $me->id;
            $user->approved_at = now();
        }

        $user->save();
        AuditLog::log('users', $user->id, 'update', $user->getOriginal(), $user->only(['name','phone','role_code','admin_level','status_code','region_id']));
        return redirect()->route('admin.users.show', $user)->with('success', '저장되었습니다.');
    }

    // -------------------- 상태 변경 액션 (안전장치 포함) --------------------
    public function approve(User $user, NotificationService $notify)
    {
        $before = ['status_code' => $user->status_code];
        $user->status_code = 'active';
        $user->approved_by = auth()->id();
        $user->approved_at = now();
        $user->save();

        AuditLog::log('users', $user->id, 'approve', $before, ['status_code' => 'active']);

        $notify->send('user.approval_result', [
            'name'   => $user->name,
            'result' => '승인',
        ], [
            ['type' => 'user', 'id' => $user->id, 'phone' => $user->phone, 'email' => $user->email],
        ]);

        return back()->with('success', "{$user->name}({$user->login_id}) 승인 완료");
    }

    public function reject(User $user, NotificationService $notify)
    {
        if (! $this->canModify($user, 'reject')) {
            return back()->with('error', '본인 또는 슈퍼관리자 계정은 거절할 수 없습니다.');
        }
        $before = ['status_code' => $user->status_code];
        $user->status_code = 'terminated';
        $user->save();

        AuditLog::log('users', $user->id, 'reject', $before, ['status_code' => 'terminated']);

        $notify->send('user.approval_result', [
            'name'   => $user->name,
            'result' => '거절',
        ], [
            ['type' => 'user', 'id' => $user->id, 'phone' => $user->phone, 'email' => $user->email],
        ]);

        return back()->with('success', "{$user->name}({$user->login_id}) 거절 처리됨");
    }

    public function suspend(User $user)
    {
        if (! $this->canModify($user, 'suspend')) {
            return back()->with('error', '본인 또는 슈퍼관리자 계정은 일시정지할 수 없습니다.');
        }
        $before = ['status_code' => $user->status_code];
        $user->status_code = 'suspended';
        $user->save();
        AuditLog::log('users', $user->id, 'suspend', $before, ['status_code' => 'suspended']);
        return back()->with('success', "{$user->name}({$user->login_id}) 일시정지");
    }

    public function activate(User $user)
    {
        $before = ['status_code' => $user->status_code];
        $user->status_code = 'active';
        $user->save();
        AuditLog::log('users', $user->id, 'activate', $before, ['status_code' => 'active']);
        return back()->with('success', "{$user->name}({$user->login_id}) 정상화");
    }

    public function resetPassword(User $user)
    {
        if (! $this->canModify($user, 'reset')) {
            return back()->with('error', '본인 또는 슈퍼관리자 계정의 비번은 여기서 초기화할 수 없습니다.');
        }
        // 영문+숫자 혼합 8자 임시 비번 생성
        $new = $this->generateTempPassword(8);
        $user->password = $new; // hashed cast
        $user->password_change_required = true; // 다음 로그인 시 변경 강제
        $user->save();

        AuditLog::log('users', $user->id, 'reset_password', null, null);

        return back()
            ->with('success', "비밀번호가 초기화되었습니다. 새 비밀번호: {$new} (1회만 표시됨)")
            ->with('new_password', $new);
    }

    /** 영문 4 + 숫자 4 자리 혼합 임시 비밀번호 (정책 준수) */
    private function generateTempPassword(int $length = 8): string
    {
        $letters = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
        $digits  = '23456789';
        $half    = (int) floor($length / 2);
        $pw  = substr(str_shuffle(str_repeat($letters, 4)), 0, $half);
        $pw .= substr(str_shuffle(str_repeat($digits,  4)), 0, $length - $half);
        return str_shuffle($pw);
    }

    // -------------------- GUARDS --------------------
    private function canModify(User $target, string $action): bool
    {
        $me = auth()->user();
        // 본인 차단
        if ($target->id === $me->id) {
            return false;
        }
        // 슈퍼관리자 보호: 슈퍼관리자가 아닌 사람이 슈퍼관리자에게 영향 금지
        if ($target->isSuperAdmin() && ! $me->isSuperAdmin()) {
            return false;
        }
        return true;
    }
}
