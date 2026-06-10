<?php

namespace App\Services;

use App\Models\Book;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * 관리자용 재고 일괄 등록 서비스 (다중 총판)
 *
 * 엑셀 컬럼 (A → D):
 *   A: ISBN13     — books.isbn 매칭
 *   B: 총판명     — users.name 매칭 (role_code='distributor')
 *   C: 수량       — book_stocks.qty
 *   D: 안전재고   — book_stocks.low_stock_threshold (선택, 기본 0)
 *
 * 처리: book + 총판 조합 있으면 UPDATE, 없으면 INSERT
 * (기존 StockImportService는 총판 본인용 — admin은 별도)
 */
class StockBulkImportService
{
    public const COLUMN_MAP = [
        'ISBN13'    => 'isbn',
        'ISBN'      => 'isbn',
        '바코드'     => 'isbn',
        '총판명'     => 'distributor_name',
        '총판'       => 'distributor_name',
        '수량'       => 'qty',
        '재고'       => 'qty',
        '재고수량'   => 'qty',
        '보유수량'   => 'qty',
        '안전재고'   => 'low_stock_threshold',
        '안전수량'   => 'low_stock_threshold',
    ];

    public const TEMPLATE_HEADERS = ['ISBN13', '총판명', '수량', '안전재고'];

    /** 위치 기반 매핑 (헤더 매칭 실패 시 fallback) */
    public const POSITION_MAP = [
        0 => 'isbn',                 // A
        1 => 'distributor_name',     // B
        2 => 'qty',                  // C
        3 => 'low_stock_threshold',  // D
    ];

    /** @return array{rows: array, errors: array, total: int} */
    public function parse(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $data  = $sheet->toArray(null, true, true, false);
        if (empty($data)) {
            return ['rows' => [], 'errors' => [['row' => 0, 'msg' => '엑셀이 비어있습니다.']], 'total' => 0];
        }

        $header = array_map(fn ($h) => trim((string) $h), array_shift($data));
        $colIdx = [];

        // 1차: 헤더 이름 매칭 (대소문자/공백 무시)
        $normalizedMap = [];
        foreach (self::COLUMN_MAP as $key => $field) {
            $normalizedMap[mb_strtolower(preg_replace('/\s+/', '', $key))] = $field;
        }
        foreach ($header as $i => $h) {
            $normH = mb_strtolower(preg_replace('/\s+/', '', $h));
            if (isset($normalizedMap[$normH])) $colIdx[$normalizedMap[$normH]] = $i;
        }
        // 2차: 위치 기반 fallback
        foreach (self::POSITION_MAP as $pos => $field) {
            if (! isset($colIdx[$field]) && $pos < count($header)) {
                $colIdx[$field] = $pos;
            }
        }

        if (! isset($colIdx['isbn']) || ! isset($colIdx['distributor_name']) || ! isset($colIdx['qty'])) {
            return ['rows' => [], 'errors' => [
                ['row' => 1, 'msg' => '필수 컬럼 누락 — ISBN/총판명/수량. 헤더: '.implode('|', $header)]
            ], 'total' => 0];
        }

        // 총판명 → user_id 캐시
        $distMap = User::where('role_code', 'distributor')
            ->whereNull('deleted_at')
            ->pluck('id', 'name')->toArray();

        $rows = [];
        $errors = [];
        $rowNum = 1;
        foreach ($data as $r) {
            $rowNum++;
            if (count(array_filter($r, fn ($v) => trim((string) $v) !== '')) === 0) continue;

            $row = [];
            foreach ($colIdx as $field => $i) {
                $row[$field] = isset($r[$i]) ? (is_string($r[$i]) ? trim($r[$i]) : $r[$i]) : null;
            }

            $rowErrors = [];

            // ISBN → book 매칭
            $isbn = preg_replace('/[^0-9Xx]/', '', (string) ($row['isbn'] ?? ''));
            $book = null;
            if ($isbn) {
                $book = Book::where('isbn', $isbn)->whereNull('deleted_at')->first();
            }
            if (! $book) {
                $rowErrors[] = "ISBN '{$isbn}' 매칭되는 도서 없음";
            }

            // 총판명 → user 매칭
            $distName = trim((string) ($row['distributor_name'] ?? ''));
            $distId = $distMap[$distName] ?? null;
            if (! $distId) {
                $rowErrors[] = "총판 '{$distName}' 매칭 안됨";
            }

            // 수량
            $qty = (int) preg_replace('/[^\d-]/', '', (string) ($row['qty'] ?? 0));
            if ($qty < 0) $rowErrors[] = '수량은 0 이상';

            // 안전재고 (선택)
            $threshold = null;
            if (! empty($row['low_stock_threshold']) && $row['low_stock_threshold'] !== '') {
                $threshold = (int) preg_replace('/[^\d]/', '', (string) $row['low_stock_threshold']);
            }

            $row['_row']       = $rowNum;
            $row['_book']      = $book ? ['id' => $book->id, 'title' => $book->title, 'isbn' => $book->isbn] : null;
            $row['_dist_id']   = $distId;
            $row['_dist_name'] = $distName;
            $row['_qty']       = $qty;
            $row['_threshold'] = $threshold;
            $row['_errors']    = $rowErrors;
            $row['_exists']    = ($book && $distId) ? DB::table('book_stocks')
                ->where('book_id', $book->id)->where('distributor_user_id', $distId)->exists() : false;

            if ($rowErrors) {
                $errors[] = ['row' => $rowNum, 'msg' => implode(', ', $rowErrors)];
            }
            $rows[] = $row;
        }

        return ['rows' => $rows, 'errors' => $errors, 'total' => count($rows)];
    }

    /** @return array{success: int, updated: int, failed: int, errors: array} */
    public function import(array $rows, string $mode = 'upsert'): array
    {
        $success = 0; $updated = 0; $failed = 0; $errors = [];

        foreach ($rows as $row) {
            if (! empty($row['_errors'])) { $failed++; continue; }

            try {
                $bookId    = $row['_book']['id'];
                $distId    = $row['_dist_id'];
                $qty       = $row['_qty'];
                $threshold = $row['_threshold'];

                $existing = DB::table('book_stocks')
                    ->where('book_id', $bookId)
                    ->where('distributor_user_id', $distId)
                    ->first();

                if ($existing) {
                    if ($mode === 'skip_existing') { continue; }
                    $update = ['qty' => $qty, 'updated_at' => now()];
                    if ($threshold !== null) $update['low_stock_threshold'] = $threshold;
                    DB::table('book_stocks')->where('id', $existing->id)->update($update);
                    $updated++;
                } else {
                    DB::table('book_stocks')->insert([
                        'book_id'             => $bookId,
                        'distributor_user_id' => $distId,
                        'qty'                 => $qty,
                        'low_stock_threshold' => $threshold ?? 0,
                        'reserved_qty'        => 0,
                        'created_at'          => now(),
                        'updated_at'          => now(),
                    ]);
                    $success++;
                }
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = ['row' => $row['_row'] ?? '?', 'msg' => $e->getMessage()];
            }
        }
        return compact('success', 'updated', 'failed', 'errors');
    }

    public function buildTemplate(): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('재고 일괄 등록');

        foreach (self::TEMPLATE_HEADERS as $i => $h) {
            $col = chr(ord('A') + $i);
            $sheet->setCellValue($col.'1', $h);
        }
        $sheet->setCellValue('A2', '9788901234001');
        $sheet->setCellValue('B2', '한국도서총판');
        $sheet->setCellValue('C2', 100);
        $sheet->setCellValue('D2', 10);

        $sheet->getStyle('A1:D1')->getFont()->setBold(true);
        $sheet->getStyle('A1:D1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('EAF0FA');
        foreach (range('A', 'D') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        return $spreadsheet;
    }
}
