<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\DistributorScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * 대표 이용학원 등록 (영업자·총판)
 *
 * 학원을 아는 건 영업자라 등록은 현장에서 하게 두되,
 * **랜딩 노출 여부는 관리자만 정한다** — 회사 홈페이지에 나가는 내용이고
 * 학원 이름·로고를 쓰는 데는 그 학원의 동의가 필요하기 때문.
 * 그래서 여기서 올린 건 '노출 대기'로 쌓이고, 관리자가 켜야 랜딩에 나온다.
 *
 * 스코프: 영업자 = 본인이 올린 것 / 총판 = 본인 + 산하 영업자가 올린 것
 */
class FeaturedAcademyController extends Controller
{
    private function authorizeUser(): User
    {
        $user = Auth::user();
        if (! $user || ! in_array($user->role_code, ['agent', 'distributor'], true)) {
            abort(403, '영업자 또는 총판만 사용할 수 있습니다.');
        }
        return $user;
    }

    /** 이 사람이 다룰 수 있는 등록자 id 목록 */
    private function ownerIds(User $user): array
    {
        if ($user->role_code === 'agent') return [$user->id];
        return array_values(array_unique(array_merge(
            [$user->id], DistributorScopeService::agentIds($user->id)
        )));
    }

    private function ownedOrFail(User $user, int $id): object
    {
        $row = DB::table('featured_academies')->find($id);
        abort_if(! $row, 404);
        abort_if(! in_array((int) $row->created_by_user_id, $this->ownerIds($user), true), 403,
                 '본인이 등록한 학원만 수정할 수 있습니다.');
        return $row;
    }

    public function index()
    {
        $user = $this->authorizeUser();

        $rows = DB::table('featured_academies as f')
            ->leftJoin('regions as r', 'r.id', '=', 'f.region_id')
            ->leftJoin('users as u', 'u.id', '=', 'f.created_by_user_id')
            ->whereIn('f.created_by_user_id', $this->ownerIds($user))
            ->orderByDesc('f.id')
            ->get(['f.*', 'r.name as region_name', 'u.name as owner_name']);

        return view('public.mypage.featured_academies', [
            'user'    => $user,
            'rows'    => $rows,
            'regions' => DB::table('regions')->where('level', 'sido')->where('is_active', 1)
                            ->orderBy('sort_order')->get(['id', 'name']),
            'waiting' => $rows->where('is_active', 0)->count(),
        ]);
    }

    public function store(Request $request)
    {
        $user = $this->authorizeUser();
        $data = $this->validated($request);

        // 노출은 관리자 몫 — 여기서 올린 건 항상 대기 상태로 들어간다
        $data['is_active']          = 0;
        $data['created_by_user_id'] = $user->id;
        $data['created_at']         = now();
        $data['updated_at']         = now();

        if ($request->hasFile('logo_file')) {
            $data['logo_path'] = $request->file('logo_file')->store('academies', 'public');
        }

        $id = DB::table('featured_academies')->insertGetId($data);
        AuditLog::log('featured_academies', $id, 'create_by_partner', null,
            ['name' => $data['name'], 'by' => $user->login_id]);

        return back()->with('success', "'{$data['name']}' 등록했습니다. 관리자 확인 후 홈페이지에 노출됩니다.");
    }

    public function update(Request $request, int $id)
    {
        $user = $this->authorizeUser();
        $row  = $this->ownedOrFail($user, $id);

        $data = $this->validated($request);
        $data['updated_at'] = now();

        if ($request->hasFile('logo_file')) {
            if ($row->logo_path) Storage::disk('public')->delete($row->logo_path);
            $data['logo_path'] = $request->file('logo_file')->store('academies', 'public');
        }

        DB::table('featured_academies')->where('id', $id)->update($data);
        AuditLog::log('featured_academies', $id, 'update_by_partner', (array) $row, $data);

        return back()->with('success', "'{$data['name']}' 수정했습니다.");
    }

    public function destroy(int $id)
    {
        $user = $this->authorizeUser();
        $row  = $this->ownedOrFail($user, $id);

        if ($row->logo_path) Storage::disk('public')->delete($row->logo_path);
        DB::table('featured_academies')->where('id', $id)->delete();
        AuditLog::log('featured_academies', $id, 'delete_by_partner', (array) $row, null);

        return back()->with('success', "'{$row->name}' 삭제했습니다.");
    }

    /** 노출 토글은 없다 — is_active 는 관리자만 건드린다 */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:120'],
            'region_id'    => ['nullable', 'integer', 'exists:regions,id'],
            'city'         => ['nullable', 'string', 'max:60'],
            'homepage_url' => ['nullable', 'url', 'max:255'],
            'memo'         => ['nullable', 'string', 'max:255'],
            'logo_file'    => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
        ]);

        return [
            'name'         => $data['name'],
            'region_id'    => $data['region_id'] ?? null,
            'city'         => $data['city'] ?? null,
            'homepage_url' => $data['homepage_url'] ?? null,
            'memo'         => $data['memo'] ?? null,
        ];
    }
}
