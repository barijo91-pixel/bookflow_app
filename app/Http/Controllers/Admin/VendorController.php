<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class VendorController extends Controller
{
    // -------------------- LIST --------------------
    public function index(Request $request)
    {
        $type = $request->query('type');
        $status = $request->query('status');
        $q = trim((string) $request->query('q'));

        $allowedSorts = ['id', 'name', 'owner_name', 'business_no', 'mobile', 'type_code', 'status_code', 'created_at'];
        $sort = $request->query('sort', 'id');
        $dir  = $request->query('dir', 'desc');
        if (! in_array($sort, $allowedSorts, true)) $sort = 'id';
        if (! in_array($dir, ['asc', 'desc'], true)) $dir = 'desc';

        $query = Vendor::query()->orderBy($sort, $dir);
        if ($sort !== 'id') $query->orderByDesc('id');

        if ($type)   { $query->where('type_code', $type); }
        if ($status) { $query->where('status_code', $status); }
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                  ->orWhere('owner_name', 'like', "%{$q}%")
                  ->orWhere('business_no', 'like', "%{$q}%")
                  ->orWhere('mobile', 'like', "%{$q}%");
            });
        }

        $vendors = $query->paginate(20)->withQueryString();
        $typeOptions   = DB::table('codes')->where('group_code', 'vendor_type')->orderBy('sort_order')->get();
        $statusOptions = DB::table('codes')->where('group_code', 'vendor_status')->orderBy('sort_order')->get();

        return view('admin.vendors.index', compact('vendors', 'typeOptions', 'statusOptions', 'type', 'status', 'q', 'sort', 'dir'));
    }

    // -------------------- CREATE --------------------
    public function create()
    {
        $typeOptions   = DB::table('codes')->where('group_code', 'vendor_type')->orderBy('sort_order')->get();
        $bankOptions   = DB::table('codes')->where('group_code', 'bank')->orderBy('sort_order')->get();
        $sidos         = DB::table('regions')->where('level', 'sido')->orderBy('sort_order')->get();
        return view('admin.vendors.create', compact('typeOptions', 'bankOptions', 'sidos'));
    }

    public function store(Request $request)
    {
        $data = $this->validatePayload($request);
        $data['status_code'] = 'active';

        $vendor = Vendor::create($data);
        return redirect()->route('admin.vendors.show', $vendor)->with('success', '거래처가 등록되었습니다.');
    }

    // -------------------- SHOW (편집 + 담당자 + 영업자 할인율 + 주문) --------------------
    public function show(Vendor $vendor)
    {
        $typeOptions   = DB::table('codes')->where('group_code', 'vendor_type')->orderBy('sort_order')->get();
        $statusOptions = DB::table('codes')->where('group_code', 'vendor_status')->orderBy('sort_order')->get();
        $bankOptions   = DB::table('codes')->where('group_code', 'bank')->orderBy('sort_order')->get();
        $sidos         = DB::table('regions')->where('level', 'sido')->orderBy('sort_order')->get();

        // 현재 region 부모(sido) 찾기
        $currentSidoId = null;
        $sigungus = collect();
        if ($vendor->region_id) {
            $reg = DB::table('regions')->find($vendor->region_id);
            if ($reg && $reg->parent_id) {
                $currentSidoId = $reg->parent_id;
                $sigungus = DB::table('regions')->where('parent_id', $currentSidoId)->orderBy('sort_order')->get();
            }
        }

        // 담당자(vendor_users)
        $staffs = DB::table('vendor_users as vu')
            ->join('users as u', 'u.id', '=', 'vu.user_id')
            ->where('vu.vendor_id', $vendor->id)
            ->select('vu.id as link_id','vu.role','vu.is_primary','u.id as user_id','u.name','u.email','u.phone','u.status_code')
            ->orderByDesc('vu.is_primary')->orderBy('vu.id')->get();

        // 후보: academy 역할 + 이 vendor에 아직 연결 안 된 사용자
        $linkedUserIds = $staffs->pluck('user_id');
        $candidateStaffs = User::where('role_code', 'academy')
            ->whereNotIn('id', $linkedUserIds)
            ->orderBy('name')->get(['id','name','login_id','email','phone']);

        // 영업자 매핑 + 할인율
        $agentLinks = DB::table('agent_vendor_discounts as avd')
            ->join('users as u', 'u.id', '=', 'avd.agent_user_id')
            ->where('avd.vendor_id', $vendor->id)
            ->select('avd.id','avd.discount_rate','avd.is_active','avd.started_at','avd.ended_at',
                'u.id as agent_id','u.name','u.email','u.phone')
            ->orderByDesc('avd.is_active')->orderBy('avd.id')->get();

        $linkedAgentIds = $agentLinks->pluck('agent_id');
        $candidateAgents = User::where('role_code', 'agent')
            ->where('status_code', 'active')
            ->whereNotIn('id', $linkedAgentIds)
            ->orderBy('name')->get(['id','name','login_id','email']);

        // 최근 주문 10건
        $recentOrders = DB::table('orders')
            ->where('vendor_id', $vendor->id)
            ->orderByDesc('id')->limit(10)->get();

        return view('admin.vendors.show', compact(
            'vendor','typeOptions','statusOptions','bankOptions','sidos','currentSidoId','sigungus',
            'staffs','candidateStaffs','agentLinks','candidateAgents','recentOrders'
        ));
    }

    // -------------------- UPDATE --------------------
    public function update(Request $request, Vendor $vendor)
    {
        $data = $this->validatePayload($request, $vendor->id);
        $data['status_code'] = $request->validate([
            'status_code' => ['required', Rule::in(['active','suspended','terminated'])],
        ])['status_code'];

        $vendor->update($data);
        return redirect()->route('admin.vendors.show', $vendor)->with('success', '저장되었습니다.');
    }

    public function destroy(Vendor $vendor)
    {
        if (DB::table('orders')->where('vendor_id', $vendor->id)->exists()) {
            return back()->with('error', '주문 이력이 있는 거래처는 삭제할 수 없습니다. 상태를 "거래종료"로 변경하세요.');
        }
        $vendor->delete();
        return redirect()->route('admin.vendors.index')->with('success', '거래처가 삭제되었습니다.');
    }

    // -------------------- 담당자(vendor_users) --------------------
    public function attachStaff(Request $request, Vendor $vendor)
    {
        $data = $request->validate([
            'user_id'    => ['required', 'integer', 'exists:users,id'],
            'role'       => ['required', Rule::in(['owner','manager','staff'])],
            'is_primary' => ['nullable', 'boolean'],
        ]);
        $isPrimary = $request->boolean('is_primary');

        // 중복 매핑 방지
        if (DB::table('vendor_users')->where('vendor_id', $vendor->id)->where('user_id', $data['user_id'])->exists()) {
            return back()->with('error', '이미 등록된 담당자입니다.');
        }
        // 사용자 역할 확인
        $u = User::find($data['user_id']);
        if (! $u || $u->role_code !== 'academy') {
            return back()->with('error', 'academy 역할의 사용자만 담당자로 추가할 수 있습니다.');
        }

        if ($isPrimary) {
            DB::table('vendor_users')->where('vendor_id', $vendor->id)->update(['is_primary' => false, 'updated_at' => now()]);
        }
        DB::table('vendor_users')->insert([
            'vendor_id'  => $vendor->id,
            'user_id'    => $data['user_id'],
            'role'       => $data['role'],
            'is_primary' => $isPrimary,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return back()->with('success', '담당자가 추가되었습니다.');
    }

    public function detachStaff(Vendor $vendor, int $linkId)
    {
        DB::table('vendor_users')->where('id', $linkId)->where('vendor_id', $vendor->id)->delete();
        return back()->with('success', '담당자가 제거되었습니다.');
    }

    // -------------------- 영업자 매핑 + 할인율 --------------------
    public function attachAgent(Request $request, Vendor $vendor)
    {
        $data = $request->validate([
            'agent_user_id' => ['required', 'integer', 'exists:users,id'],
            'discount_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        if (DB::table('agent_vendor_discounts')
            ->where('vendor_id', $vendor->id)
            ->where('agent_user_id', $data['agent_user_id'])
            ->exists()) {
            return back()->with('error', '이미 매핑된 영업자입니다.');
        }
        $u = User::find($data['agent_user_id']);
        if (! $u || $u->role_code !== 'agent') {
            return back()->with('error', 'agent 역할의 사용자만 매핑할 수 있습니다.');
        }

        DB::table('agent_vendor_discounts')->insert([
            'agent_user_id' => $data['agent_user_id'],
            'vendor_id'     => $vendor->id,
            'discount_rate' => $data['discount_rate'],
            'started_at'    => now()->toDateString(),
            'is_active'     => true,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
        return back()->with('success', '영업자가 매핑되었습니다.');
    }

    public function updateAgentRate(Request $request, Vendor $vendor, int $linkId)
    {
        $data = $request->validate([
            'discount_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_active'     => ['nullable', 'boolean'],
        ]);
        DB::table('agent_vendor_discounts')
            ->where('id', $linkId)->where('vendor_id', $vendor->id)
            ->update([
                'discount_rate' => $data['discount_rate'],
                'is_active'     => $request->boolean('is_active'),
                'ended_at'      => $request->boolean('is_active') ? null : now()->toDateString(),
                'updated_at'    => now(),
            ]);
        return back()->with('success', '할인율이 수정되었습니다.');
    }

    public function detachAgent(Vendor $vendor, int $linkId)
    {
        // 주문 이력이 있으면 비활성화만, 없으면 삭제
        $row = DB::table('agent_vendor_discounts')->where('id', $linkId)->where('vendor_id', $vendor->id)->first();
        if (! $row) abort(404);
        $hasOrders = DB::table('orders')
            ->where('vendor_id', $vendor->id)
            ->where('agent_user_id', $row->agent_user_id)
            ->exists();
        if ($hasOrders) {
            DB::table('agent_vendor_discounts')->where('id', $linkId)->update([
                'is_active' => false,
                'ended_at'  => now()->toDateString(),
                'updated_at' => now(),
            ]);
            return back()->with('success', '주문 이력이 있어 비활성화 처리했습니다.');
        }
        DB::table('agent_vendor_discounts')->where('id', $linkId)->delete();
        return back()->with('success', '영업자 매핑이 해제되었습니다.');
    }

    // -------------------- helpers --------------------
    private function validatePayload(Request $request, ?int $vendorId = null): array
    {
        return $request->validate([
            'name'           => ['required', 'string', 'max:150'],
            'owner_name'     => ['nullable', 'string', 'max:100'],
            'business_no'    => ['nullable', 'string', 'max:20'],
            'type_code'      => ['required', 'string', 'max:30'],
            'biz_type'       => ['nullable', 'string', 'max:100'],
            'biz_item'       => ['nullable', 'string', 'max:100'],
            'mobile'         => ['nullable', 'string', 'max:20'],
            'tel'            => ['nullable', 'string', 'max:20'],
            'region_id'      => ['nullable', 'integer', 'exists:regions,id'],
            'address'        => ['nullable', 'string', 'max:255'],
            'address_detail' => ['nullable', 'string', 'max:255'],
            'bank_code'      => ['nullable', 'string', 'max:10'],
            'bank_account'   => ['nullable', 'string', 'max:50'],
            'bank_holder'    => ['nullable', 'string', 'max:50'],
            'memo'           => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
