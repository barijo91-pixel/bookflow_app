<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CodeController extends Controller
{
    public function index(string $groupCode)
    {
        $group = DB::table('code_groups')->where('group_code', $groupCode)->first();
        abort_if(! $group, 404);

        $codes = DB::table('codes')
            ->where('group_code', $groupCode)
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();

        return view('admin.codes.codes', compact('group', 'codes'));
    }

    public function store(Request $request, string $groupCode)
    {
        abort_if(! DB::table('code_groups')->where('group_code', $groupCode)->exists(), 404);

        $data = $request->validate([
            'code'       => ['required', 'string', 'max:50',
                Rule::unique('codes', 'code')->where(fn ($q) => $q->where('group_code', $groupCode))],
            'name'       => ['required', 'string', 'max:100'],
            'value'      => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer'],
        ]);
        $data['group_code'] = $groupCode;
        $data['sort_order'] = $data['sort_order'] ?? 999;
        $data['is_active'] = true;
        $data['created_at'] = now();
        $data['updated_at'] = now();
        DB::table('codes')->insert($data);

        return back()->with('success', '코드가 추가되었습니다.');
    }

    public function update(Request $request, string $groupCode, int $id)
    {
        $code = DB::table('codes')->where('id', $id)->where('group_code', $groupCode)->first();
        abort_if(! $code, 404);

        $data = $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'value'      => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer'],
            'is_active'  => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $data['updated_at'] = now();
        DB::table('codes')->where('id', $id)->update($data);

        return back()->with('success', '코드가 수정되었습니다.');
    }

    public function destroy(string $groupCode, int $id)
    {
        $code = DB::table('codes')->where('id', $id)->where('group_code', $groupCode)->first();
        abort_if(! $code, 404);
        DB::table('codes')->where('id', $id)->delete();
        return back()->with('success', '코드가 삭제되었습니다.');
    }
}
