<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\UserImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UserImportController extends Controller
{
    public function __construct(private UserImportService $importer) {}

    /** 업로드 화면 */
    public function show()
    {
        $recentJobs = DB::table('bulk_import_jobs')
            ->where('kind', 'user')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return view('admin.users.import', compact('recentJobs'));
    }

    /** 템플릿 다운로드 */
    public function template()
    {
        $path = $this->importer->generateTemplate();
        return response()->download($path, 'user_import_template.xlsx')->deleteFileAfterSend();
    }

    /** 파일 업로드 → 미리보기 */
    public function preview(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ]);

        $file = $request->file('file');
        $storedPath = $file->store('imports', 'local');
        $absPath = Storage::disk('local')->path($storedPath);

        $result = $this->importer->parse($absPath);

        $jobId = DB::table('bulk_import_jobs')->insertGetId([
            'created_by'    => auth()->id(),
            'kind'          => 'user',
            'file_path'     => $storedPath,
            'original_name' => $file->getClientOriginalName(),
            'status'        => 'pending',
            'total_rows'    => $result['total'],
            'failed_rows'   => count($result['errors']),
            'errors'        => json_encode($result['errors'], JSON_UNESCAPED_UNICODE),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $request->session()->put("import.user.{$jobId}", $result['rows']);

        return view('admin.users.import_preview', [
            'jobId'  => $jobId,
            'rows'   => $result['rows'],
            'errors' => $result['errors'],
            'total'  => $result['total'],
            'file'   => $file->getClientOriginalName(),
        ]);
    }

    /** 실제 import 실행 */
    public function run(Request $request, int $jobId)
    {
        $job = DB::table('bulk_import_jobs')->where('id', $jobId)->where('kind', 'user')->first();
        abort_if(! $job, 404);

        $rows = $request->session()->get("import.user.{$jobId}");
        if (! $rows) {
            return redirect()->route('admin.users.import.show')->with('error', '미리보기 세션이 만료되었습니다. 다시 업로드해주세요.');
        }

        $result = $this->importer->import($rows);

        DB::table('bulk_import_jobs')->where('id', $jobId)->update([
            'status'        => 'done',
            'success_rows'  => $result['success'],
            'failed_rows'   => $result['failed'],
            'errors'        => json_encode($result['errors'], JSON_UNESCAPED_UNICODE),
            'updated_at'    => now(),
        ]);

        $request->session()->forget("import.user.{$jobId}");

        return view('admin.users.import_done', [
            'jobId'         => $jobId,
            'result'        => $result,
            'createdUsers'  => $result['created_users'],
        ]);
    }
}
