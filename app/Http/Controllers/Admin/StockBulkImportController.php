<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\StockBulkImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * 관리자용 재고 일괄 등록 (다중 총판)
 * 도서 엑셀 업로드와 동일 패턴
 */
class StockBulkImportController extends Controller
{
    public function __construct(private StockBulkImportService $importer) {}

    public function show()
    {
        $recentJobs = DB::table('bulk_import_jobs')
            ->where('kind', 'stock')->orderByDesc('id')->limit(10)->get();
        return view('admin.stocks.import', compact('recentJobs'));
    }

    public function template()
    {
        $spreadsheet = $this->importer->buildTemplate();
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, '재고_일괄_등록_템플릿.xlsx', [
            'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'        => 'no-cache',
        ]);
    }

    public function preview(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240']]);

        $file = $request->file('file');
        $storedPath = $file->store('imports', 'local');
        $absPath = Storage::disk('local')->path($storedPath);
        $result = $this->importer->parse($absPath);

        $jobId = DB::table('bulk_import_jobs')->insertGetId([
            'created_by'    => auth()->id(),
            'kind'          => 'stock',
            'file_path'     => $storedPath,
            'original_name' => $file->getClientOriginalName(),
            'status'        => 'pending',
            'total_rows'    => $result['total'],
            'failed_rows'   => count($result['errors']),
            'errors'        => json_encode($result['errors'], JSON_UNESCAPED_UNICODE),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // 파일에 미리보기 데이터 저장 (도서와 동일 패턴)
        $previewPath = "imports/preview/stock_{$jobId}.json";
        Storage::disk('local')->put($previewPath, json_encode($result['rows'], JSON_UNESCAPED_UNICODE));

        return view('admin.stocks.import_preview', [
            'jobId'  => $jobId,
            'rows'   => $result['rows'],
            'errors' => $result['errors'],
            'total'  => $result['total'],
            'file'   => $file->getClientOriginalName(),
        ]);
    }

    public function run(Request $request, int $jobId)
    {
        $job = DB::table('bulk_import_jobs')->where('id', $jobId)->where('kind', 'stock')->first();
        abort_if(! $job, 404);

        $previewPath = "imports/preview/stock_{$jobId}.json";
        if (! Storage::disk('local')->exists($previewPath)) {
            return back()->with('error', '미리보기 데이터를 찾을 수 없습니다. 파일을 다시 업로드하세요.');
        }
        $rows = json_decode(Storage::disk('local')->get($previewPath), true);
        if (! is_array($rows) || empty($rows)) {
            return back()->with('error', '미리보기 데이터가 손상되었습니다.');
        }

        $mode = $request->input('mode', 'upsert'); // upsert | skip_existing

        DB::table('bulk_import_jobs')->where('id', $jobId)->update([
            'status' => 'running', 'started_at' => now(),
            'mapping' => json_encode(['mode' => $mode], JSON_UNESCAPED_UNICODE),
        ]);

        $result = $this->importer->import($rows, $mode);

        DB::table('bulk_import_jobs')->where('id', $jobId)->update([
            'status'        => 'done',
            'success_rows'  => $result['success'] + $result['updated'],
            'failed_rows'   => $result['failed'],
            'finished_at'   => now(),
            'errors'        => json_encode($result['errors'], JSON_UNESCAPED_UNICODE),
            'updated_at'    => now(),
        ]);

        Storage::disk('local')->delete($previewPath);

        return redirect()->route('admin.stocks.index')->with('success',
            "재고 일괄 등록 완료 — 신규 {$result['success']}건, 수정 {$result['updated']}건, 실패 {$result['failed']}건"
        );
    }
}
