<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\StockImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class StockImportController extends Controller
{
    public function __construct(private StockImportService $importer) {}

    private function authorizeDist(): \App\Models\User
    {
        $user = Auth::user();
        if (! $user || $user->role_code !== 'distributor') {
            abort(403, '총판만 접근 가능합니다.');
        }
        return $user;
    }

    /** 업로드 화면 */
    public function show()
    {
        $user = $this->authorizeDist();
        $recentJobs = DB::table('bulk_import_jobs')
            ->where('kind', 'stock')
            ->where('created_by', $user->id)
            ->orderByDesc('id')->limit(5)->get();
        $currentCount = DB::table('book_stocks')->where('distributor_user_id', $user->id)->count();

        return view('public.mypage.stock_import', compact('user', 'recentJobs', 'currentCount'));
    }

    /** 템플릿 다운로드 */
    public function template()
    {
        $this->authorizeDist();
        $spreadsheet = $this->importer->buildTemplate();
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, '재고_등록_템플릿.xlsx', [
            'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'        => 'no-cache',
        ]);
    }

    /** 업로드 → 미리보기 */
    public function preview(Request $request)
    {
        $user = $this->authorizeDist();
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240']]);

        $file = $request->file('file');
        $storedPath = $file->store('imports', 'local');
        $absPath = Storage::disk('local')->path($storedPath);

        $result = $this->importer->parse($absPath, $user->id);

        $jobId = DB::table('bulk_import_jobs')->insertGetId([
            'created_by'    => $user->id,
            'kind'          => 'stock',
            'file_path'     => $storedPath,
            'original_name' => $file->getClientOriginalName(),
            'status'        => 'pending',
            'total_rows'    => $result['total'],
            'failed_rows'   => count($result['errors']),
            'errors'        => json_encode($result['errors'], JSON_UNESCAPED_UNICODE),
            'mapping'       => json_encode(['distributor_user_id' => $user->id], JSON_UNESCAPED_UNICODE),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $request->session()->put("import.stock.{$jobId}", $result['rows']);

        return view('public.mypage.stock_import_preview', [
            'jobId'  => $jobId,
            'rows'   => $result['rows'],
            'errors' => $result['errors'],
            'total'  => $result['total'],
            'file'   => $file->getClientOriginalName(),
        ]);
    }

    /** 실제 등록 */
    public function run(Request $request, int $jobId)
    {
        $user = $this->authorizeDist();
        $job = DB::table('bulk_import_jobs')->where('id', $jobId)->where('kind', 'stock')
            ->where('created_by', $user->id)->first();
        abort_if(! $job, 404);

        $rows = $request->session()->get("import.stock.{$jobId}");
        if (! $rows) {
            return redirect()->route('my.stocks.import.show')
                ->with('error', '미리보기 세션이 만료되었습니다. 다시 업로드해주세요.');
        }

        $result = $this->importer->import($rows, $user->id);

        DB::table('bulk_import_jobs')->where('id', $jobId)->update([
            'status'        => 'done',
            'success_rows'  => $result['success'] + $result['updated'],
            'failed_rows'   => $result['failed'],
            'finished_at'   => now(),
            'errors'        => json_encode($result['errors'], JSON_UNESCAPED_UNICODE),
            'updated_at'    => now(),
        ]);

        $request->session()->forget("import.stock.{$jobId}");

        return redirect()->route('my.stocks.index')->with('success',
            "재고 일괄 등록 완료 — 신규 {$result['success']}건, 수정 {$result['updated']}건, 실패 {$result['failed']}건"
        );
    }
}
