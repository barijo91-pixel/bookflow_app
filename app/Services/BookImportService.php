<?php

namespace App\Services;

use App\Models\Book;
use App\Models\Publisher;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class BookImportService
{
    /** 엑셀 헤더 컬럼명 → 내부 필드 매핑 */
    public const COLUMN_MAP = [
        'ISBN13'        => 'isbn',
        'ISBN'          => 'isbn',
        '출판사코드'      => 'publisher_code',
        '출판사 코드'     => 'publisher_code',
        '품목코드'        => 'publisher_code',
        '도서코드'        => 'publisher_code',
        '제목'           => 'title',
        '시리즈'          => 'series_name',
        '시리즈명'         => 'series_name',
        '출판사'          => 'publisher_name',
        '정가'           => 'price',
        '학교'           => 'school_code',
        '과목'           => 'subject_code',
        '학년'           => 'grade_codes',     // 쉼표 구분
        '난이도'          => 'level_codes',     // 쉼표 구분
        '상태'           => 'status_code',
        '표지URL'        => 'cover_path',
        '표지'           => 'cover_path',
        '규격'           => 'spec',
        '판쇄'           => 'edition',
    ];

    public const TEMPLATE_HEADERS = [
        'ISBN13', '출판사코드', '제목', '시리즈명', '출판사', '정가',
        '학교', '과목', '학년', '난이도', '상태', '표지URL', '규격', '판쇄',
    ];

    /** 학교/과목/학년/난이도/상태 코드 매핑 (한글명 → code) */
    private array $codeMaps = [];

    public function __construct()
    {
        foreach (['school','subject','grade','level','book_status'] as $g) {
            $this->codeMaps[$g] = DB::table('codes')
                ->where('group_code', $g)
                ->pluck('code', 'name')
                ->toArray();
        }
    }

    /**
     * 엑셀 파일 파싱 → 행 배열 + 검증 결과
     * @return array{rows: array, errors: array, total: int}
     */
    public function parse(string $filePath): array
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

        if (! isset($colIdx['isbn']) || ! isset($colIdx['title']) || ! isset($colIdx['price'])) {
            return ['rows' => [], 'errors' => [
                ['row' => 1, 'msg' => '필수 헤더 누락: ISBN13, 제목, 정가가 필요합니다. 헤더: '.implode('|', $header)]
            ], 'total' => 0];
        }

        $rows = [];
        $errors = [];
        $rowNumber = 1;
        foreach ($data as $r) {
            $rowNumber++;
            if (count(array_filter($r, fn ($v) => trim((string) $v) !== '')) === 0) continue;

            $row = [];
            foreach ($colIdx as $field => $i) {
                $row[$field] = isset($r[$i]) ? (is_string($r[$i]) ? trim($r[$i]) : $r[$i]) : null;
            }

            // 검증
            $rowErrors = [];
            $isbn = preg_replace('/[^0-9Xx]/', '', (string) ($row['isbn'] ?? ''));
            if (! $isbn || strlen($isbn) !== 13) {
                $rowErrors[] = 'ISBN13이 올바르지 않음';
            }
            $row['isbn'] = $isbn;

            if (empty($row['title'])) $rowErrors[] = '제목 없음';
            if (! isset($row['price']) || ! is_numeric($row['price'])) {
                $rowErrors[] = '정가가 숫자가 아님';
            }

            // 코드 매핑: 한글명 → code
            foreach (['school_code' => 'school', 'subject_code' => 'subject', 'status_code' => 'book_status'] as $field => $group) {
                if (! empty($row[$field])) {
                    $val = trim((string) $row[$field]);
                    if (isset($this->codeMaps[$group][$val])) {
                        $row[$field] = $this->codeMaps[$group][$val];
                    } elseif (in_array($val, $this->codeMaps[$group], true)) {
                        // 이미 code로 입력된 경우 그대로 유지
                    } else {
                        $rowErrors[] = "{$group} 값 '{$val}' 매칭 안됨";
                    }
                }
            }

            // 학년/난이도 (쉼표 구분)
            $row['grade_codes'] = $this->mapList($row['grade_codes'] ?? null, 'grade', $rowErrors, '학년');
            $row['level_codes'] = $this->mapList($row['level_codes'] ?? null, 'level', $rowErrors, '난이도');

            // 출판사 코드 정리 (공백 제거 + 50자 cap)
            if (! empty($row['publisher_code'])) {
                $row['publisher_code'] = mb_substr(trim((string) $row['publisher_code']), 0, 50);
            }

            $row['_row'] = $rowNumber;
            $row['_errors'] = $rowErrors;
            $row['_exists'] = Book::where('isbn', $row['isbn'])->exists();

            if ($rowErrors) {
                $errors[] = ['row' => $rowNumber, 'msg' => implode(', ', $rowErrors), 'data' => $row];
            }
            $rows[] = $row;
        }

        return ['rows' => $rows, 'errors' => $errors, 'total' => count($rows)];
    }

    /**
     * 실제 import 수행 (검증 통과 행만)
     * @return array{success: int, updated: int, failed: int, errors: array}
     */
    public function import(array $rows, string $mode = 'skip_existing'): array
    {
        $success = 0; $updated = 0; $failed = 0; $errors = [];

        foreach ($rows as $row) {
            if (! empty($row['_errors'])) { $failed++; continue; }

            try {
                $publisherId = null;
                if (! empty($row['publisher_name'])) {
                    $pub = Publisher::firstOrCreate(
                        ['name' => $row['publisher_name']],
                        ['sort_order' => 999, 'is_active' => true]
                    );
                    $publisherId = $pub->id;
                }

                $payload = [
                    'title'          => $row['title'] ?? '',
                    'series_name'    => $row['series_name'] ?? null,
                    'publisher_id'   => $publisherId,
                    'publisher_code' => $row['publisher_code'] ?? null,
                    'price'          => (int) ($row['price'] ?? 0),
                    'school_code'    => $row['school_code'] ?? null,
                    'subject_code'   => $row['subject_code'] ?? null,
                    'status_code'    => $row['status_code'] ?? 'selling',
                    'cover_path'     => $row['cover_path'] ?? null,
                    'spec'           => $row['spec'] ?? null,
                    'edition'        => $row['edition'] ?? null,
                    'source'         => 'excel',
                ];

                $book = Book::where('isbn', $row['isbn'])->first();
                if ($book) {
                    if ($mode === 'skip_existing') { continue; }
                    $book->update($payload);
                    $updated++;
                } else {
                    $book = Book::create(array_merge($payload, ['isbn' => $row['isbn']]));
                    $success++;
                }

                // book_targets 갱신
                DB::table('book_targets')->where('book_id', $book->id)->delete();
                $now = now();
                $targets = [];
                foreach (($row['grade_codes'] ?? []) as $g) {
                    $targets[] = ['book_id' => $book->id, 'target_type' => 'grade', 'code' => $g, 'created_at' => $now, 'updated_at' => $now];
                }
                foreach (($row['level_codes'] ?? []) as $l) {
                    $targets[] = ['book_id' => $book->id, 'target_type' => 'level', 'code' => $l, 'created_at' => $now, 'updated_at' => $now];
                }
                if (! empty($row['school_code'])) {
                    $targets[] = ['book_id' => $book->id, 'target_type' => 'school', 'code' => $row['school_code'], 'created_at' => $now, 'updated_at' => $now];
                }
                if ($targets) DB::table('book_targets')->insert($targets);

            } catch (\Throwable $e) {
                $failed++;
                $errors[] = ['row' => $row['_row'] ?? '?', 'msg' => $e->getMessage()];
            }
        }
        return ['success' => $success, 'updated' => $updated, 'failed' => $failed, 'errors' => $errors];
    }

    private function mapList($value, string $group, array &$errors, string $label): array
    {
        if (empty($value)) return [];
        $items = array_filter(array_map('trim', preg_split('/[,;\/]+/', (string) $value)));
        $codes = [];
        foreach ($items as $v) {
            if (isset($this->codeMaps[$group][$v])) {
                $codes[] = $this->codeMaps[$group][$v];
            } elseif (in_array($v, $this->codeMaps[$group], true)) {
                $codes[] = $v;
            } else {
                $errors[] = "{$label} '{$v}' 매칭 안됨";
            }
        }
        return array_unique($codes);
    }

    private function normalizeDate($value): ?string
    {
        if (is_numeric($value)) {
            try {
                $dt = ExcelDate::excelToDateTimeObject((float) $value);
                return $dt->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }
        $s = (string) $value;
        if (preg_match('/^(\d{4})[-.\/]?(\d{1,2})[-.\/]?(\d{1,2})/', $s, $m)) {
            return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        }
        return null;
    }

    /** 빈 템플릿 Spreadsheet 객체 (streamDownload용 — 컨트롤러에서 직접 출력) */
    public function buildTemplate(): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('도서 일괄 등록');

        foreach (self::TEMPLATE_HEADERS as $i => $h) {
            $col = chr(ord('A') + $i);
            $sheet->setCellValue($col.'1', $h);
        }
        // 예시 1행 — 헤더: ISBN13, 출판사코드, 제목, 시리즈명, 출판사, 정가,
        //                  학교, 과목, 학년, 난이도, 상태, 표지URL, 규격, 판쇄
        $sheet->setCellValue('A2', '9788901234001');
        $sheet->setCellValue('B2', 'B00150000003');     // 출판사 자체 도서코드 (총판 주문용)
        $sheet->setCellValue('C2', 'Bricks Phonics 1');
        $sheet->setCellValue('D2', 'Bricks Phonics');
        $sheet->setCellValue('E2', '브릭스');
        $sheet->setCellValue('F2', 12000);
        $sheet->setCellValue('G2', '초등');
        $sheet->setCellValue('H2', '영어');
        $sheet->setCellValue('I2', '예비초, 초1');
        $sheet->setCellValue('J2', '입문');
        $sheet->setCellValue('K2', '판매중');

        // 헤더 스타일 — 컬럼 수 = 14개 (A~N)
        $sheet->getStyle('A1:N1')->getFont()->setBold(true);
        $sheet->getStyle('A1:N1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('EAF0FA');

        foreach (range('A', 'N') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    /** 후방 호환 — 임시 파일 경로 반환 (sys_get_temp_dir 사용, 운영 권한 무관) */
    public function generateTemplate(): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'book_tpl_').'.xlsx';
        $writer = IOFactory::createWriter($this->buildTemplate(), 'Xlsx');
        $writer->save($tmp);
        return $tmp;
    }
}
