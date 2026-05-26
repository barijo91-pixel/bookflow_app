<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\StudentImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class StudentImportController extends Controller
{
    public function __construct(private StudentImportService $importer) {}

    /**
     * 권한 + 학급/학원 식별:
     * - 학원: 본인 vendor의 class만 접근
     * - 영업자: 본인 담당(active) vendor의 class만 접근
     * @return array{user:\App\Models\User, vendorId:int, class:object}
     */
    private function authorizeClass(int $classId): array
    {
        $user = Auth::user();
        $class = DB::table('academy_classes')->where('id', $classId)->first();
        if (! $class) abort(404, '학급을 찾을 수 없습니다.');

        $ok = false;
        if ($user->role_code === 'academy') {
            $vendorId = DB::table('vendor_users')->where('user_id', $user->id)->value('vendor_id');
            $ok = $vendorId && (int) $vendorId === (int) $class->vendor_id;
        } elseif ($user->role_code === 'agent') {
            $ok = DB::table('agent_vendor_discounts')
                ->where('agent_user_id', $user->id)
                ->where('vendor_id', $class->vendor_id)
                ->where('is_active', true)
                ->exists();
        }
        if (! $ok) abort(403, '이 학급에 학생을 등록할 권한이 없습니다.');

        return [$user, (int) $class->vendor_id, $class];
    }

    /** 학생 일괄 등록 화면 (학원·영업자 공용) */
    public function show(int $classId)
    {
        [$user, $vendorId, $class] = $this->authorizeClass($classId);
        $vendor = DB::table('vendors')->find($vendorId);
        $recentJobs = DB::table('bulk_import_jobs')
            ->where('kind', 'student')
            ->where('mapping', 'like', '%"class_id":'.$classId.'%')
            ->orderByDesc('id')->limit(5)->get();
        $currentCount = DB::table('students')->where('class_id', $classId)->whereNull('deleted_at')->count();

        return view('public.mypage.student_import', compact('user', 'class', 'vendor', 'recentJobs', 'currentCount'));
    }

    /** 템플릿 다운로드 (streamDownload — 디스크 무관) */
    public function template(int $classId)
    {
        $this->authorizeClass($classId);
        $spreadsheet = $this->importer->buildTemplate();
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, '학생_등록_템플릿.xlsx', [
            'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'        => 'no-cache',
        ]);
    }

    /** 업로드 → 미리보기 */
    public function preview(Request $request, int $classId)
    {
        [$user, $vendorId, $class] = $this->authorizeClass($classId);

        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240']]);

        $file = $request->file('file');
        $storedPath = $file->store('imports', 'local');
        $absPath = Storage::disk('local')->path($storedPath);

        $result = $this->importer->parse($absPath, $classId, $vendorId);

        $jobId = DB::table('bulk_import_jobs')->insertGetId([
            'created_by'    => $user->id,
            'kind'          => 'student',
            'file_path'     => $storedPath,
            'original_name' => $file->getClientOriginalName(),
            'status'        => 'pending',
            'total_rows'    => $result['total'],
            'failed_rows'   => count($result['errors']),
            'errors'        => json_encode($result['errors'], JSON_UNESCAPED_UNICODE),
            'mapping'       => json_encode(['class_id' => $classId, 'vendor_id' => $vendorId], JSON_UNESCAPED_UNICODE),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $request->session()->put("import.student.{$jobId}", $result['rows']);

        return view('public.mypage.student_import_preview', [
            'class'   => $class,
            'jobId'   => $jobId,
            'rows'    => $result['rows'],
            'errors'  => $result['errors'],
            'total'   => $result['total'],
            'file'    => $file->getClientOriginalName(),
        ]);
    }

    /** 실제 등록 실행 */
    public function run(Request $request, int $classId, int $jobId)
    {
        [$user, $vendorId, $class] = $this->authorizeClass($classId);

        $job = DB::table('bulk_import_jobs')->where('id', $jobId)->where('kind', 'student')->first();
        abort_if(! $job, 404);

        $rows = $request->session()->get("import.student.{$jobId}");
        if (! $rows) {
            return redirect()->route('my.classes.students.import.show', $classId)
                ->with('error', '미리보기 세션이 만료되었습니다. 다시 업로드해주세요.');
        }

        $result = $this->importer->import($rows, $classId, $vendorId);

        DB::table('bulk_import_jobs')->where('id', $jobId)->update([
            'status'        => 'done',
            'success_rows'  => $result['success'],
            'failed_rows'   => $result['failed'],
            'finished_at'   => now(),
            'errors'        => json_encode($result['errors'], JSON_UNESCAPED_UNICODE),
            'updated_at'    => now(),
        ]);

        $request->session()->forget("import.student.{$jobId}");

        return redirect()->route('my.classes.show', $classId)->with('success',
            "학생 일괄 등록 완료 — 성공 {$result['success']}건, 실패 {$result['failed']}건"
        );
    }

    /** 영업자: 학원·학급 선택 화면 (사이드바 진입점) */
    public function agentSelect()
    {
        $user = Auth::user();
        if ($user->role_code !== 'agent') abort(403, '영업자만 접근 가능합니다.');

        $vendors = DB::table('agent_vendor_discounts as avd')
            ->join('vendors as v', 'v.id', '=', 'avd.vendor_id')
            ->where('avd.agent_user_id', $user->id)
            ->where('avd.is_active', true)
            ->select('v.id', 'v.name')->orderBy('v.name')->get();

        $vendorClasses = []; // [vendor_id => [classes]]
        foreach ($vendors as $v) {
            $vendorClasses[$v->id] = DB::table('academy_classes')
                ->where('vendor_id', $v->id)
                ->where('status', 'active')
                ->orderBy('name')
                ->select('id', 'name', 'grade_code',
                    DB::raw('(SELECT COUNT(*) FROM students WHERE class_id = academy_classes.id AND deleted_at IS NULL) as student_count'))
                ->get();
        }

        return view('public.mypage.student_import_agent_select', compact('user', 'vendors', 'vendorClasses'));
    }
}
