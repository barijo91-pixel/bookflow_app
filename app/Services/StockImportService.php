<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class StockImportService
{
    public const COLUMN_MAP = [
        'ISBN'        => 'isbn',
        'ISBN13'      => 'isbn',
        '바코드'       => 'isbn',
        '보유수량'    => 'qty',
        '수량'        => 'qty',
        '재고수량'    => 'qty',
        '안전재고'    => 'low_stock_threshold',
        '최소재고'    => 'low_stock_threshold',
        '메모'        => 'memo',
    ];

    public const TEMPLATE_HEADERS = ['ISBN', '보유수량', '안전재고', '메모'];

    /** 템플릿 Spreadsheet 객체 (streamDownload용) */
    public function buildTemplate(): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('재고 일괄 등록');

        foreach (self::TEMPLATE_HEADERS as $i => $h) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue($col.'1', $h);
        }

        $samples = [
            ['9788937834790', 100, 10, '주력 도서'],
            ['9788937834806',  50,  5, null],
        ];
        foreach ($samples as $rowIdx => $sample) {
            foreach ($sample as $colIdx => $val) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 1);
                $sheet->setCellValue($col.($rowIdx + 2), $val);
            }
        }

        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count(self::TEMPLATE_HEADERS));
        $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastCol}1")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('EAF0FA');
        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $info = $spreadsheet->createSheet();
        $info->setTitle('안내');
        $info->setCellValue('A1', '재고 일괄 등록 가이드');
        $info->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $info->setCellValue('A3', '필수: ISBN, 보유수량');
        $info->setCellValue('A4', '선택: 안전재고(기본 0), 메모');
        $info->setCellValue('A6', 'ISBN은 BookSys에 이미 등록된 도서여야 합니다. 없는 도서는 관리자에게 등록 요청.');
        $info->setCellValue('A7', '같은 ISBN이 본인 재고에 이미 있으면 보유수량/안전재고가 덮어쓰기됩니다.');
        $info->getColumnDimension('A')->setAutoSize(true);

        $spreadsheet->setActiveSheetIndex(0);
        return $spreadsheet;
    }

    /**
     * @return array{rows:array, errors:array, total:int}
     */
    public function parse(string $filePath, int $distributorUserId): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $data = $sheet->toArray(null, true, true, false);
        if (empty($data)) {
            return ['rows' => [], 'errors' => [['row' => 0, 'msg' => '엑셀이 비어있습니다.']], 'total' => 0];
        }

        $header = array_map(fn ($h) => trim((string) $h), array_shift($data));
        $colIdx = [];
        foreach ($header as $i => $h) {
            if (isset(self::COLUMN_MAP[$h])) {
                $colIdx[self::COLUMN_MAP[$h]] = $i;
            }
        }

        if (! isset($colIdx['isbn']) || ! isset($colIdx['qty'])) {
            return ['rows' => [], 'errors' => [
                ['row' => 1, 'msg' => '필수 헤더 누락: ISBN, 보유수량. 헤더: '.implode('|', $header)]
            ], 'total' => 0];
        }

        $rows = [];
        $errors = [];
        $rowNumber = 1;
        $seenIsbns = [];
        $existingStocks = DB::table('book_stocks as s')
            ->join('books as b', 'b.id', '=', 's.book_id')
            ->where('s.distributor_user_id', $distributorUserId)
            ->pluck('b.isbn', 's.id')->flip()->toArray(); // [isbn => stock_id]

        foreach ($data as $r) {
            $rowNumber++;
            if (count(array_filter($r, fn ($v) => trim((string) $v) !== '')) === 0) continue;

            $row = [];
            foreach ($colIdx as $field => $i) {
                $row[$field] = isset($r[$i]) ? (is_string($r[$i]) ? trim($r[$i]) : $r[$i]) : null;
            }

            $rowErrors = [];

            // ISBN 검증
            $isbn = preg_replace('/[^0-9Xx]/', '', (string) ($row['isbn'] ?? ''));
            if (! $isbn || (strlen($isbn) !== 13 && strlen($isbn) !== 10)) {
                $rowErrors[] = 'ISBN 형식 오류 (13자 또는 10자)';
            } else {
                $book = DB::table('books')->whereNull('deleted_at')->where('isbn', $isbn)
                    ->select('id', 'title')->first();
                if (! $book) {
                    $rowErrors[] = "ISBN {$isbn} 도서를 찾을 수 없음 (관리자에 등록 요청 필요)";
                } else {
                    $row['book_id']    = $book->id;
                    $row['book_title'] = $book->title;
                }
                if (in_array($isbn, $seenIsbns, true)) {
                    $rowErrors[] = "엑셀 내 ISBN '{$isbn}' 중복";
                } else {
                    $seenIsbns[] = $isbn;
                }
            }
            $row['isbn'] = $isbn;
            $row['already_exists'] = isset($existingStocks[$isbn]);

            // 수량
            $qty = $row['qty'] ?? null;
            if (! is_numeric($qty) || (int) $qty < 0) {
                $rowErrors[] = '보유수량은 0 이상 숫자';
            } else {
                $row['qty'] = (int) $qty;
            }

            // 안전재고
            $ss = $row['low_stock_threshold'] ?? null;
            if ($ss !== null && $ss !== '') {
                if (! is_numeric($ss) || (int) $ss < 0) {
                    $rowErrors[] = '안전재고는 0 이상 숫자';
                } else {
                    $row['low_stock_threshold'] = (int) $ss;
                }
            } else {
                $row['low_stock_threshold'] = 0;
            }

            $row['_row'] = $rowNumber;
            $row['_errors'] = $rowErrors;

            if ($rowErrors) {
                $errors[] = ['row' => $rowNumber, 'msg' => implode(', ', $rowErrors)];
            }
            $rows[] = $row;
        }

        return ['rows' => $rows, 'errors' => $errors, 'total' => count($rows)];
    }

    /**
     * @return array{success:int, updated:int, failed:int, errors:array}
     */
    public function import(array $rows, int $distributorUserId): array
    {
        $success = 0; $updated = 0; $failed = 0; $errors = [];

        foreach ($rows as $row) {
            if (! empty($row['_errors'])) { $failed++; continue; }

            try {
                $existing = DB::table('book_stocks')
                    ->where('distributor_user_id', $distributorUserId)
                    ->where('book_id', $row['book_id'])
                    ->first();
                if ($existing) {
                    DB::table('book_stocks')->where('id', $existing->id)->update([
                        'qty'          => $row['qty'],
                        'low_stock_threshold' => $row['low_stock_threshold'],
                        'updated_at'   => now(),
                    ]);
                    $updated++;
                } else {
                    DB::table('book_stocks')->insert([
                        'distributor_user_id' => $distributorUserId,
                        'book_id'             => $row['book_id'],
                        'qty'                 => $row['qty'],
                        'low_stock_threshold'        => $row['low_stock_threshold'],
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

        return ['success' => $success, 'updated' => $updated, 'failed' => $failed, 'errors' => $errors];
    }
}
