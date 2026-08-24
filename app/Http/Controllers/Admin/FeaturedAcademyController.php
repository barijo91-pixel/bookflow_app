<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * 대표 이용학원 관리 — 랜딩에 노출되는 홍보용 목록.
 *
 * 이미지는 나중에 채워 넣을 수 있게 이름만으로도 등록된다
 * (로고가 없으면 랜딩에서 이름 카드로 표시).
 */
class FeaturedAcademyController extends Controller
{
    private function regions()
    {
        return DB::table('regions')->where('level', 'sido')->where('is_active', 1)
            ->orderBy('sort_order')->get(['id', 'name']);
    }

    public function index(Request $request)
    {
        $regionId = (int) $request->query('region_id') ?: null;

        $rows = DB::table('featured_academies as f')
            ->leftJoin('regions as r', 'r.id', '=', 'f.region_id')
            ->when($regionId, fn ($q) => $q->where('f.region_id', $regionId))
            ->orderBy('r.sort_order')->orderBy('f.sort_order')->orderBy('f.id')
            ->get(['f.*', 'r.name as region_name']);

        return view('admin.featured_academies.index', [
            'rows'     => $rows,
            'regions'  => $this->regions(),
            'regionId' => $regionId,
            'counts'   => [
                'total'  => DB::table('featured_academies')->count(),
                'active' => DB::table('featured_academies')->where('is_active', 1)->count(),
                'logo'   => DB::table('featured_academies')->whereNotNull('logo_path')->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['created_at'] = now();
        $data['updated_at'] = now();

        if ($request->hasFile('logo_file')) {
            $data['logo_path'] = $request->file('logo_file')->store('academies', 'public');
        }

        $id = DB::table('featured_academies')->insertGetId($data);
        AuditLog::log('featured_academies', $id, 'create', null, ['name' => $data['name']]);

        return back()->with('success', "'{$data['name']}' 등록했습니다.");
    }

    public function update(Request $request, int $id)
    {
        $row = DB::table('featured_academies')->find($id);
        abort_if(! $row, 404);

        $data = $this->validated($request);
        $data['updated_at'] = now();

        if ($request->hasFile('logo_file')) {
            // 갈아끼울 때 옛 파일은 지운다 — storage 에 고아 이미지가 쌓이지 않게
            if ($row->logo_path) Storage::disk('public')->delete($row->logo_path);
            $data['logo_path'] = $request->file('logo_file')->store('academies', 'public');
        }
        if ($request->boolean('remove_logo') && $row->logo_path) {
            Storage::disk('public')->delete($row->logo_path);
            $data['logo_path'] = null;
        }

        DB::table('featured_academies')->where('id', $id)->update($data);
        AuditLog::log('featured_academies', $id, 'update', (array) $row, $data);

        return back()->with('success', "'{$data['name']}' 수정했습니다.");
    }

    public function destroy(int $id)
    {
        $row = DB::table('featured_academies')->find($id);
        abort_if(! $row, 404);

        if ($row->logo_path) Storage::disk('public')->delete($row->logo_path);
        DB::table('featured_academies')->where('id', $id)->delete();
        AuditLog::log('featured_academies', $id, 'delete', (array) $row, null);

        return back()->with('success', "'{$row->name}' 삭제했습니다.");
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:120'],
            'region_id'    => ['nullable', 'integer', 'exists:regions,id'],
            'city'         => ['nullable', 'string', 'max:60'],
            'homepage_url' => ['nullable', 'url', 'max:255'],
            'sort_order'   => ['nullable', 'integer', 'min:0', 'max:99999'],
            'memo'         => ['nullable', 'string', 'max:255'],
            // 로고는 이미지 확장자만 — 업로드 경로로 실행 파일이 들어오지 않게
            'logo_file'    => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
        ]);

        return [
            'name'         => $data['name'],
            'region_id'    => $data['region_id'] ?? null,
            'city'         => $data['city'] ?? null,
            'homepage_url' => $data['homepage_url'] ?? null,
            'sort_order'   => (int) ($data['sort_order'] ?? 0),
            'memo'         => $data['memo'] ?? null,
            'is_active'    => $request->boolean('is_active'),
        ];
    }
}
