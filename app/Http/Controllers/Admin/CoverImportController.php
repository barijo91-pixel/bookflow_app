<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AladinService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * 도서 표지 이미지 일괄 업로드/매칭 컨트롤러
 *
 * 매칭 우선순위:
 * 1. ZIP 안에 books.cover_file_name 으로 등록된 파일 (엑셀에서 지정한 파일명)
 * 2. ZIP 안에 {ISBN}.{확장자} 파일 (자동 매칭)
 * 3. ZIP 안에 {publisher_code}.{확장자} 파일 (자동 매칭)
 * 4. (옵션) 알라딘 API로 표지 URL 자동 조회
 */
class CoverImportController extends Controller
{
    public function __construct(private AladinService $aladin) {}

    /** 업로드 화면 */
    public function show()
    {
        $total      = DB::table('books')->whereNull('deleted_at')->count();
        $withCover  = DB::table('books')->whereNull('deleted_at')
                        ->whereNotNull('cover_path')->where('cover_path', '!=', '')->count();
        $noCover    = $total - $withCover;

        return view('admin.books.covers_upload', compact('total', 'withCover', 'noCover'));
    }

    /** ZIP 업로드 + 매칭 실행 */
    public function upload(Request $request)
    {
        $request->validate([
            'zip'             => ['required', 'file', 'mimes:zip', 'max:512000'], // 500MB
            'try_aladdin'     => ['nullable', 'in:0,1'],
            'overwrite'       => ['nullable', 'in:0,1'],
        ]);

        $tryAladdin = $request->input('try_aladdin') === '1';
        $overwrite  = $request->input('overwrite') === '1';

        // ZIP 파일 저장
        $zipFile = $request->file('zip');
        $jobId   = uniqid();
        $zipPath = $zipFile->store("covers/uploads", 'local');
        $absZip  = Storage::disk('local')->path($zipPath);

        // 압축 해제
        $extractDir = storage_path("app/private/covers/extracted_{$jobId}");
        if (! is_dir($extractDir)) mkdir($extractDir, 0775, true);

        $zip = new ZipArchive();
        if ($zip->open($absZip) !== true) {
            return back()->with('error', 'ZIP 파일을 열 수 없습니다.');
        }
        $zip->extractTo($extractDir);
        $zip->close();

        // ZIP 안 모든 이미지 파일 인덱싱 (서브폴더 포함, 파일명 → 경로 맵)
        $fileMap = $this->buildFileIndex($extractDir);

        // 표지가 없는 (또는 overwrite 옵션 시 전체) 도서 순회
        $query = DB::table('books')->whereNull('deleted_at');
        if (! $overwrite) {
            $query->where(function ($w) {
                $w->whereNull('cover_path')->orWhere('cover_path', '');
            });
        }
        $books = $query->get();

        $success = 0;
        $aladdinSuccess = 0;
        $missing = [];

        foreach ($books as $book) {
            // 후보 파일명 (우선순위)
            $candidates = [];
            if (! empty($book->cover_file_name)) {
                $candidates[] = $book->cover_file_name;
            }
            // ISBN 자동 매칭 (확장자 후보)
            foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
                $candidates[] = $book->isbn . '.' . $ext;
            }
            // publisher_code 매칭
            if (! empty($book->publisher_code)) {
                foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
                    $candidates[] = $book->publisher_code . '.' . $ext;
                }
            }

            $found = null;
            foreach ($candidates as $name) {
                $key = mb_strtolower($name);
                if (isset($fileMap[$key])) {
                    $found = $fileMap[$key];
                    break;
                }
            }

            if ($found) {
                // storage/app/public/covers/{isbn}.{ext} 로 복사
                $ext = strtolower(pathinfo($found, PATHINFO_EXTENSION));
                $safeIsbn = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $book->isbn);
                $destRel  = "covers/{$safeIsbn}.{$ext}";
                Storage::disk('public')->put($destRel, file_get_contents($found));

                DB::table('books')->where('id', $book->id)->update([
                    'cover_path' => $destRel,
                    'updated_at' => now(),
                ]);
                $success++;
            } elseif ($tryAladdin && ! str_starts_with($book->isbn, 'NOISBN-')) {
                // 알라딘 API 시도 (실제 ISBN만)
                try {
                    $item = $this->aladin->lookupByIsbn($book->isbn);
                    if (! empty($item['cover'])) {
                        DB::table('books')->where('id', $book->id)->update([
                            'cover_path' => $item['cover'], // URL 그대로 저장
                            'updated_at' => now(),
                        ]);
                        $aladdinSuccess++;
                    } else {
                        $missing[] = $book->isbn;
                    }
                } catch (\Throwable $e) {
                    $missing[] = $book->isbn;
                }
            } else {
                $missing[] = $book->isbn;
            }
        }

        // 임시 폴더 정리
        $this->rrmdir($extractDir);
        Storage::disk('local')->delete($zipPath);

        return redirect()->route('admin.books.covers.show')->with([
            'success' => "표지 매칭 완료 — ZIP {$success}건, 알라딘 {$aladdinSuccess}건, 누락 ".count($missing)."건",
            'missing_sample' => array_slice($missing, 0, 20),
            'missing_total' => count($missing),
        ]);
    }

    /** ZIP 안 파일들을 {파일명소문자 → 절대경로} 맵으로 구성 (서브폴더 재귀) */
    private function buildFileIndex(string $dir): array
    {
        $map = [];
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iter as $file) {
            if (! $file->isFile()) continue;
            $ext = strtolower($file->getExtension());
            if (! in_array($ext, ['jpg','jpeg','png','webp'], true)) continue;
            $map[mb_strtolower($file->getFilename())] = $file->getPathname();
        }
        return $map;
    }

    /** 디렉토리 재귀 삭제 */
    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) return;
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
