<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BookImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * 도서 마스터 엑셀 다운로드
 *
 * 컬럼 순서를 업로드 템플릿(BookImportService::TEMPLATE_HEADERS)과 동일하게 맞춰
 * 내려받아 수정 후 그대로 재업로드할 수 있게 한다.
 * 현재 화면의 필터(출판사/상태/학교/과목/검색)를 그대로 적용해 내보낸다.
 */
class BookExportController extends Controller
{
    public function export(Request $request)
    {
        $q         = trim((string) $request->query('q'));
        $publisher = $request->query('publisher');
        $status    = $request->query('status');
        $school    = $request->query('school');
        $subject   = $request->query('subject');

        $rows = DB::table('books as b')
            ->leftJoin('publishers as p', 'p.id', '=', 'b.publisher_id')
            ->whereNull('b.deleted_at')
            ->when($publisher, fn ($w) => $w->where('b.publisher_id', $publisher))
            ->when($status,    fn ($w) => $w->where('b.status_code', $status))
            ->when($school,    fn ($w) => $w->where('b.school_code', $school))
            ->when($subject,   fn ($w) => $w->where('b.subject_code', $subject))
            ->when($q !== '', function ($w) use ($q) {
                $w->where(function ($s) use ($q) {
                    $s->where('b.title', 'like', "%{$q}%")
                      ->orWhere('b.isbn', 'like', "%{$q}%")
                      ->orWhere('b.author', 'like', "%{$q}%")
                      ->orWhere('b.series_name', 'like', "%{$q}%")
                      ->orWhere('b.publisher_code', 'like', "%{$q}%");
                });
            })
            ->orderBy('p.name')->orderBy('b.title')
            ->get([
                'b.isbn', 'b.publisher_code', 'b.title', 'b.subtitle', 'b.series_name',
                'p.name as publisher_name', 'b.price', 'b.school_code', 'b.subject_code',
                'b.status_code', 'b.author', 'b.spec', 'b.edition', 'b.cover_path',
                'b.cover_file_name', 'b.default_discount_rate', 'b.pub_date', 'b.created_at',
            ]);

        // 코드 → 이름 (학교/과목) 매핑
        $codeNames = DB::table('codes')->whereIn('group_code', ['school', 'subject', 'book_status'])
            ->pluck('name', 'code');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('도서마스터');

        // 업로드 템플릿과 동일한 헤더 + 참고용 추가 컬럼
        $headers = array_merge(
            BookImportService::TEMPLATE_HEADERS,
            ['부제', '저자', '기본할인율(%)', '발행일', '등록일']
        );
        $sheet->fromArray($headers, null, 'A1');

        $r = 2;
        foreach ($rows as $b) {
            $sheet->fromArray([
                (string) $b->isbn,
                (string) $b->publisher_code,
                $b->title,
                $b->series_name,
                $b->publisher_name,
                (int) $b->price,
                $codeNames[$b->school_code]  ?? $b->school_code,
                $codeNames[$b->subject_code] ?? $b->subject_code,
                '',                                   // 학년 (books 미보관)
                '',                                   // 난이도 (books 미보관)
                $codeNames[$b->status_code] ?? $b->status_code,
                $b->cover_path,
                $b->spec,
                $b->edition,
                $b->cover_file_name,
                // 참고용 추가
                $b->subtitle,
                $b->author,
                $b->default_discount_rate,
                $b->pub_date,
                $b->created_at ? substr((string) $b->created_at, 0, 10) : '',
            ], null, 'A' . $r);
            // ISBN/출판사코드는 숫자로 변환되지 않게 텍스트 고정
            $sheet->getCell('A' . $r)->setValueExplicit((string) $b->isbn, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->getCell('B' . $r)->setValueExplicit((string) $b->publisher_code, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $r++;
        }

        // 헤더 스타일 + 열 너비
        $lastCol = $sheet->getHighestColumn();
        $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastCol}1")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8EEF7');
        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->freezePane('A2');

        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $filename = '도서마스터_' . now()->format('Ymd_His') . '.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'        => 'no-cache',
        ]);
    }
}
