<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    private array $groupOrder = ['company', 'app', 'integration', 'seo', 'policy'];
    private array $groupLabels = [
        'company'     => '회사 정보',
        'app'         => '앱 다운로드',
        'integration' => '외부 연동',
        'seo'         => 'SEO',
        'policy'      => '정책',
    ];

    public function edit(Request $request)
    {
        $active = $request->query('group', 'company');
        if (! in_array($active, $this->groupOrder, true)) {
            $active = 'company';
        }

        $settings = SiteSetting::query()
            ->orderBy('group')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('group');

        return view('admin.settings.edit', [
            'settings'    => $settings,
            'groupOrder'  => $this->groupOrder,
            'groupLabels' => $this->groupLabels,
            'active'      => $active,
        ]);
    }

    public function update(Request $request, string $group)
    {
        abort_unless(in_array($group, $this->groupOrder, true), 404);

        $payload = (array) $request->input('settings', []);

        foreach ($payload as $key => $value) {
            $row = SiteSetting::where('key', $key)->first();
            if ($row) {
                // boolean checkbox 미체크 처리
                if ($row->type === 'boolean') {
                    $value = $value ? '1' : '0';
                }
                $row->value = $value;
                $row->save();
            }
        }

        SiteSetting::flush();
        return redirect()->route('admin.settings.edit', ['group' => $group])
            ->with('success', $this->groupLabels[$group] . ' 설정이 저장되었습니다.');
    }
}
