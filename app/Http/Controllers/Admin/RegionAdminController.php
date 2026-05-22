<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RegionAdminController extends Controller
{
    public function index(Request $request)
    {
        $sidoId = $request->query('sido');
        $q = trim((string) $request->query('q'));

        $sidos = DB::table('regions')
            ->where('level', 'sido')
            ->orderBy('sort_order')
            ->get();

        // 각 시도의 시군구 개수
        $sigCounts = DB::table('regions')
            ->where('level', 'sigungu')
            ->select('parent_id', DB::raw('count(*) as cnt'))
            ->groupBy('parent_id')
            ->pluck('cnt', 'parent_id');
        foreach ($sidos as $s) {
            $s->sg_count = $sigCounts[$s->id] ?? 0;
        }

        $sigungus = collect();
        $selectedSido = null;
        if ($sidoId) {
            $selectedSido = $sidos->firstWhere('id', (int) $sidoId);
            $query = DB::table('regions')->where('parent_id', $sidoId)->where('level', 'sigungu')->orderBy('sort_order');
            if ($q !== '') $query->where('name', 'like', "%{$q}%");
            $sigungus = $query->get();
        }

        return view('admin.regions.index', compact('sidos', 'sigungus', 'selectedSido', 'sidoId', 'q'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'      => ['required', 'string', 'max:100'],
            'level'     => ['required', Rule::in(['sido', 'sigungu'])],
            'parent_id' => ['nullable', 'integer', 'exists:regions,id'],
            'code'      => ['nullable', 'string', 'max:20'],
            'sort_order'=> ['nullable', 'integer'],
        ]);

        if ($data['level'] === 'sigungu' && empty($data['parent_id'])) {
            return back()->with('error', '시군구는 시도(parent)가 필수입니다.');
        }

        DB::table('regions')->insert([
            'parent_id'  => $data['parent_id'] ?? null,
            'level'      => $data['level'],
            'name'       => $data['name'],
            'code'       => $data['code'] ?? null,
            'sort_order' => $data['sort_order'] ?? 999,
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return back()->with('success', '지역이 추가되었습니다.');
    }

    public function update(Request $request, int $id)
    {
        $row = DB::table('regions')->find($id);
        abort_if(! $row, 404);

        $data = $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'code'       => ['nullable', 'string', 'max:20'],
            'sort_order' => ['nullable', 'integer'],
            'is_active'  => ['nullable', 'boolean'],
        ]);
        DB::table('regions')->where('id', $id)->update([
            'name'       => $data['name'],
            'code'       => $data['code'] ?? null,
            'sort_order' => $data['sort_order'] ?? $row->sort_order,
            'is_active'  => $request->boolean('is_active'),
            'updated_at' => now(),
        ]);
        return back()->with('success', '지역이 수정되었습니다.');
    }

    public function destroy(int $id)
    {
        $row = DB::table('regions')->find($id);
        abort_if(! $row, 404);

        // 사용 중인지 확인
        $userCount = DB::table('users')->where('region_id', $id)->count();
        $vendorCount = DB::table('vendors')->where('region_id', $id)->count();
        $orderCount = DB::table('orders')->where('ship_to_region_id', $id)->count();
        if ($userCount > 0 || $vendorCount > 0 || $orderCount > 0) {
            return back()->with('error', "사용 중인 지역은 삭제할 수 없습니다. (사용자 {$userCount} / 거래처 {$vendorCount} / 주문 {$orderCount})");
        }
        // 하위 시군구도 확인
        if ($row->level === 'sido' && DB::table('regions')->where('parent_id', $id)->exists()) {
            return back()->with('error', '하위 시군구가 있는 시도는 삭제할 수 없습니다.');
        }
        DB::table('regions')->where('id', $id)->delete();
        return back()->with('success', '지역이 삭제되었습니다.');
    }
}
