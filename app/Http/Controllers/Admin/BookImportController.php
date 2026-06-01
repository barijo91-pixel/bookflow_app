<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BookImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BookImportController extends Controller
{
    public function __construct(private BookImportService $importer) {}

    /** 업로드 화면 */
    public function show(Request $request)
    {
        $recentJobs = DB::table('bulk_import_jobs')
            ->where('kind', 'book')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return view('admin.books.import', compact('recentJobs'));
    }

    /** 템플릿 다운로드 — streamDownload (디스크 권한 무관, UserImport와 동일 패턴) */
    public function template()
    {
        $spreadsheet = $this->importer->buildTemplate();
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, '도서_등록_템플릿.xlsx', [
            'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'        => 'no-cache',
        ]);
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

        // 잡 기록
        $jobId = DB::table('bulk_import_jobs')->insertGetId([
            'created_by'    => auth()->id(),
            'kind'          => 'book',
            'file_path'     => $storedPath,
            'original_name' => $file->getClientOriginalName(),
            'status'        => 'pending',
            'total_rows'    => $result['total'],
            'failed_rows'   => count($result['errors']),
            'errors'        => json_encode($result['errors'], JSON_UNESCAPED_UNICODE),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // 미리보기 데이터를 세션에 보관
        $request->session()->put("import.book.{$jobId}", $result['rows']);

        return view('admin.books.import_preview', [
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
        $job = DB::table('bulk_import_jobs')->where('id', $jobId)->where('kind', 'book')->first();
        abort_if(! $job, 404);

        $rows = $request->session()->get("import.book.{$jobId}");
        if (! $rows) {
            return back()->with('error', '미리보기 세션이 만료되었습니다. 파일을 다시 업로드하세요.');
        }

        $mode = $request->input('mode', 'skip_existing'); // skip_existing | update_existing

        DB::table('bulk_import_jobs')->where('id', $jobId)->update([
            'status' => 'running',
            'started_at' => now(),
            'mapping' => json_encode(['mode' => $mode], JSON_UNESCAPED_UNICODE),
        ]);

        $result = $this->importer->import($rows, $mode);

        DB::table('bulk_import_jobs')->where('id', $jobId)->update([
            'status'        => $result['failed'] > 0 ? 'done' : 'done',
            'success_rows'  => $result['success'] + $result['updated'],
            'failed_rows'   => $result['failed'],
            'finished_at'   => now(),
            'errors'        => json_encode($result['errors'], JSON_UNESCAPED_UNICODE),
            'updated_at'    => now(),
        ]);

        $request->session()->forget("import.book.{$jobId}");

        return redirect()->route('admin.books.index')->with('success',
            "도서 일괄 등록 완료 — 신규 {$result['success']}건, 수정 {$result['updated']}건, 실패 {$result['failed']}건"
        );
    }
}
