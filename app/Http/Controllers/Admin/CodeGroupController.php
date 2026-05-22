<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CodeGroupController extends Controller
{
    public function index()
    {
        $groups = DB::table('code_groups')
            ->orderBy('sort_order')
            ->orderBy('group_code')
            ->get()
            ->map(function ($g) {
                $g->codes_count = DB::table('codes')->where('group_code', $g->group_code)->count();
                return $g;
            });

        return view('admin.codes.groups', compact('groups'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'group_code'  => ['required', 'alpha_dash', 'max:50', 'unique:code_groups,group_code'],
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order'  => ['nullable', 'integer'],
        ]);
        $data['is_system'] = false;
        $data['is_active'] = true;
        $data['sort_order'] = $data['sort_order'] ?? 999;
        $data['created_at'] = now();
        $data['updated_at'] = now();
        DB::table('code_groups')->insert($data);

        return back()->with('success', '코드 그룹이 추가되었습니다.');
    }

    public function update(Request $request, string $groupCode)
    {
        $group = DB::table('code_groups')->where('group_code', $groupCode)->first();
        abort_if(! $group, 404);

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order'  => ['nullable', 'integer'],
            'is_active'   => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $data['updated_at'] = now();
        DB::table('code_groups')->where('group_code', $groupCode)->update($data);

        return back()->with('success', '코드 그룹이 수정되었습니다.');
    }

    public function destroy(string $groupCode)
    {
        $group = DB::table('code_groups')->where('group_code', $groupCode)->first();
        abort_if(! $group, 404);
        if ($group->is_system) {
            return back()->with('error', '시스템 그룹은 삭제할 수 없습니다.');
        }
        if (DB::table('codes')->where('group_code', $groupCode)->exists()) {
            return back()->with('error', '하위 코드가 있어 삭제할 수 없습니다. 먼저 코드들을 삭제하세요.');
        }
        DB::table('code_groups')->where('group_code', $groupCode)->delete();
        return back()->with('success', '코드 그룹이 삭제되었습니다.');
    }
}
