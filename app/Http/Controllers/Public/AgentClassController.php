<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\DistributorScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * 영업자(총판)가 담당 학원의 학급을 등록·수정.
 *
 * 학원 계정용 학급 CRUD 는 MyPageController::academyVendor() 에 묶여 학원 전용이라
 * 그쪽을 건드리지 않고 영업자용 경로를 따로 둔다.
 * 학원은 자기 화면(/mypage/classes)에서 그대로 만들 수 있고, 여기서 만든 것도 동일하게 보인다.
 */
class AgentClassController extends Controller
{
    /** 이 학원을 담당하는가 — 영업자는 본인 매핑, 총판은 산하 */
    private function authorizeVendor(int $vendorId): User
    {
        $user = Auth::user();
        if (! $user) abort(403);

        if ($user->role_code === 'agent') {
            $ok = DB::table('agent_vendor_discounts')
                ->where('agent_user_id', $user->id)
                ->where('vendor_id', $vendorId)
                ->where('is_active', true)
                ->exists();
            if (! $ok) abort(404, '담당 학원이 아닙니다.');
            return $user;
        }
        if ($user->role_code === 'distributor') {
            if (! DistributorScopeService::ownsVendor($user->id, $vendorId)) {
                abort(404, '산하 학원이 아닙니다.');
            }
            return $user;
        }
        abort(403, '영업자 또는 총판만 접근 가능합니다.');
    }

    /** 학급이 속한 학원까지 확인 */
    private function authorizeClass(int $classId): array
    {
        $class = DB::table('academy_classes')->where('id', $classId)->first();
        if (! $class) abort(404, '학급을 찾을 수 없습니다.');
        $user = $this->authorizeVendor((int) $class->vendor_id);
        return [$user, $class];
    }

    /** 학급 등록 */
    public function store(Request $request, $vendorId)
    {
        $user = $this->authorizeVendor((int) $vendorId);

        // 도매는 학원이 일괄 매입하는 구조 — 학급·학생 개념이 없다
        $tradeType = DB::table('vendors')->where('id', $vendorId)->value('trade_type');
        if ($tradeType === 'wholesale') {
            return back()->with('error', '도매 학원은 학급·학생 등록이 필요 없습니다.');
        }

        $data = $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'grade_code' => ['nullable', 'string', 'max:30'],
            'memo'       => ['nullable', 'string', 'max:1000'],
        ], [], ['name' => '학급명']);

        $classId = DB::table('academy_classes')->insertGetId([
            'vendor_id'  => (int) $vendorId,
            'name'       => $data['name'],
            'grade_code' => $data['grade_code'] ?? null,
            'memo'       => $data['memo'] ?? null,
            'status'     => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        AuditLog::log('academy_classes', $classId, $user->role_code . '_create_class', null, [
            'vendor_id' => (int) $vendorId,
            'name'      => $data['name'],
        ]);

        return redirect()->route('my.agent.student.import')
            ->with('success', "학급 「{$data['name']}」이(가) 등록되었습니다. 이제 학생을 등록할 수 있습니다.");
    }

    /**
     * 담당 학원의 학급에 학생 여러 명을 한 번에 등록.
     * 학원 계정 화면(MyPageController::classAttachStudents)과 같은 동작이지만
     * 권한 기준이 달라(담당/산하 학원) 별도로 둔다.
     */
    public function attachStudents(Request $request, $classId)
    {
        [$user, $class] = $this->authorizeClass((int) $classId);

        $data = $request->validate([
            'students'                  => ['nullable', 'array'],
            'students.*.student_name'   => ['nullable', 'string', 'max:80'],
            'students.*.parent_name'    => ['nullable', 'string', 'max:80'],
            'students.*.parent_phone'   => ['nullable', 'string', 'max:20'],
            'students.*.parent_address' => ['nullable', 'string', 'max:255'],
        ]);

        $rows = collect($data['students'] ?? [])
            ->filter(fn ($r) => filled($r['student_name'] ?? null))
            ->values();

        if ($rows->isEmpty()) {
            return back()->with('error', '등록할 학생이 없습니다. 학생 이름을 입력해주세요.');
        }

        $missing = [];
        foreach ($rows as $i => $r) {
            if (blank($r['parent_name'] ?? null) || blank($r['parent_phone'] ?? null)
                || blank($r['parent_address'] ?? null)) {
                $missing[] = ($i + 1) . '번째(' . $r['student_name'] . ')';
            }
        }
        if ($missing) {
            return back()->with('error',
                '학부모 이름·연락처·주소가 모두 필요합니다 — ' . implode(', ', $missing)
                . '. 결제 요청은 연락처로, 교재는 주소로 배송됩니다.');
        }

        $now = now();
        DB::transaction(function () use ($rows, $class, $now) {
            foreach ($rows as $r) {
                $phone = preg_replace('/[^0-9]/', '', (string) $r['parent_phone']);

                $parentId = DB::table('parents')->where('phone', $phone)->whereNull('deleted_at')->value('id');
                if (! $parentId) {
                    $parentId = DB::table('parents')->insertGetId([
                        'name'       => $r['parent_name'],
                        'phone'      => $phone,
                        'address'    => $r['parent_address'] ?? null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                } elseif (filled($r['parent_address'] ?? null)) {
                    DB::table('parents')->where('id', $parentId)->update([
                        'address'    => $r['parent_address'],
                        'updated_at' => $now,
                    ]);
                }

                DB::table('students')->insert([
                    'vendor_id'  => $class->vendor_id,
                    'class_id'   => $class->id,
                    'parent_id'  => $parentId,
                    'name'       => $r['student_name'],
                    'grade_code' => $class->grade_code,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });

        AuditLog::log('academy_classes', (int) $class->id, $user->role_code . '_attach_students', null, [
            'count' => $rows->count(),
        ]);

        return redirect()->route('my.agent.student.import')
            ->with('success', "「{$class->name}」에 학생 {$rows->count()}명이 등록되었습니다.");
    }
    /** 학급 수정 (이름·학년·메모) */
    public function update(Request $request, $classId)
    {
        [$user, $class] = $this->authorizeClass((int) $classId);

        $data = $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'grade_code' => ['nullable', 'string', 'max:30'],
            'memo'       => ['nullable', 'string', 'max:1000'],
        ], [], ['name' => '학급명']);

        $before = ['name' => $class->name, 'grade_code' => $class->grade_code];
        DB::table('academy_classes')->where('id', $classId)->update([
            'name'       => $data['name'],
            'grade_code' => $data['grade_code'] ?? null,
            'memo'       => $data['memo'] ?? null,
            'updated_at' => now(),
        ]);

        AuditLog::log('academy_classes', (int) $classId, $user->role_code . '_update_class', $before, [
            'name' => $data['name'], 'grade_code' => $data['grade_code'] ?? null,
        ]);

        return redirect()->route('my.agent.student.import')
            ->with('success', '학급 정보가 수정되었습니다.');
    }
}
