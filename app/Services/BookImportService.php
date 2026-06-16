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
        '표지파일명'      => 'cover_file_name',
        '표지파일'        => 'cover_file_name',
        '이미지파일'      => 'cover_file_name',
        '이미지파일명'    => 'cover_file_name',
    ];

    public const TEMPLATE_HEADERS = [
        'ISBN13', '출판사코드', '제목', '시리즈명', '출판사', '정가',
        '학교', '과목', '학년', '난이도', '상태', '표지URL', '규격', '판쇄', '표지파일명',
    ];

    /**
     * 위치 기반 매핑 — 우리 템플릿 컬럼 순서 (헤더 이름 무관)
     * 헤더 매칭 실패 시 fallback으로 사용
     * 사용자가 우리 템플릿 그대로 사용하면 헤더가 뭐든 자동 인식
     */
    public const POSITION_MAP = [
        0  => 'isbn',            // A
        1  => 'publisher_code',  // B
        2  => 'title',           // C
        3  => 'series_name',     // D
        4  => 'publisher_name',  // E
        5  => 'price',           // F
        6  => 'school_code',     // G
        7  => 'subject_code',    // H
        8  => 'grade_codes',     // I
        9  => 'level_codes',     // J
        10 => 'status_code',     // K
        11 => 'cover_path',      // L
        12 => 'spec',            // M
        13 => 'edition',         // N
        14 => 'cover_file_name', // O — 표지 이미지 파일명 (ZIP 매칭용)
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
        // 1차: 대소문자 + 공백 무시 헤더 매칭 (정규화)
        $normalizedMap = [];
        foreach (self::COLUMN_MAP as $key => $field) {
            $normKey = mb_strtolower(preg_replace('/\s+/', '', $key));
            $normalizedMap[$normKey] = $field;
        }
        foreach ($header as $i => $h) {
            $normH = mb_strtolower(preg_replace('/\s+/', '', $h));
            if (isset($normalizedMap[$normH])) {
                $colIdx[$normalizedMap[$normH]] = $i;
            }
        }

        // 2차: 위치 기반 fallback — 헤더 매칭에 실패한 필드는 컬럼 위치로 보완
        // 사용자가 우리 템플릿 순서로 채우면 헤더 이름이 뭐든(또는 빈칸이어도) 동작
        foreach (self::POSITION_MAP as $pos => $field) {
            if (! isset($colIdx[$field]) && $pos < count($header)) {
                $colIdx[$field] = $pos;
            }
        }

        // 필수 필드 — 위치 기반으로도 보완됐으니 헤더 진짜 짧은 경우만 에러
        if (! isset($colIdx['isbn']) || ! isset($colIdx['title']) || ! isset($colIdx['price'])) {
            return ['rows' => [], 'errors' => [
                ['row' => 1, 'msg' => '엑셀 컬럼이 부족합니다. 최소 ISBN/제목/정가 컬럼 필요. 헤더: '.implode('|', $header)]
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
            // ISBN10 → ISBN13 자동 변환 (978 prefix + 체크섬 재계산)
            if (strlen($isbn) === 10) {
                $isbn = $this->convertIsbn10To13($isbn);
            }
            // ISBN 없거나 잘못된 경우 — 출판사코드로 임시 식별자 생성
            if (! $isbn || strlen($isbn) !== 13) {
                $pubCode = trim((string) ($row['publisher_code'] ?? ''));
                if ($pubCode !== '') {
                    // 영문자/숫자만 추출 (최대 12자) → NOISBN-XXXXXXXX 형태
                    $cleanCode = substr(preg_replace('/[^a-zA-Z0-9]/', '', $pubCode), 0, 12);
                    $isbn = 'NOISBN-' . $cleanCode;
                } else {
                    $rowErrors[] = 'ISBN13/출판사코드 모두 없음';
                }
            }
            $row['isbn'] = $isbn;

            if (empty($row['title'])) $rowErrors[] = '제목 없음';
            // 정가 정규화 — '54,000' '12000원' 등 숫자 외 문자 제거 후 int 변환
            if (isset($row['price'])) {
                $priceClean = preg_replace('/[^\d.]/', '', (string) $row['price']);
                if ($priceClean === '' || ! is_numeric($priceClean)) {
                    $rowErrors[] = '정가가 숫자가 아님';
                    $row['price'] = 0;
                } else {
                    $row['price'] = (int) $priceClean;
                }
            } else {
                $rowErrors[] = '정가 없음';
                $row['price'] = 0;
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
                // 부분 업데이트 지원 — 엑셀 헤더에 있는 컬럼만 payload에 포함.
                // 헤더가 없는 컬럼은 row 자체에 키가 없어 isset()이 false → DB 값 보존.
                $payload = [];
                if (array_key_exists('title', $row))          $payload['title']          = $row['title'] ?? '';
                if (array_key_exists('series_name', $row))    $payload['series_name']    = $row['series_name'];
                if (array_key_exists('publisher_code', $row)) $payload['publisher_code'] = $row['publisher_code'];
                if (array_key_exists('price', $row))          $payload['price']          = (int) $row['price'];
                if (array_key_exists('school_code', $row))    $payload['school_code']    = $row['school_code'] ?: null;
                if (array_key_exists('subject_code', $row))   $payload['subject_code']   = $row['subject_code'] ?: null;
                // status_code는 NOT NULL — 빈 값이면 payload에서 제외해 기본값('selling') 적용되게 함
                if (array_key_exists('status_code', $row) && ! empty($row['status_code'])) {
                    $payload['status_code'] = $row['status_code'];
                }
                if (array_key_exists('cover_path', $row))     $payload['cover_path']     = $row['cover_path'];
                if (array_key_exists('cover_file_name', $row))$payload['cover_file_name']= $row['cover_file_name'];
                if (array_key_exists('spec', $row))           $payload['spec']           = $row['spec'];
                if (array_key_exists('edition', $row))        $payload['edition']        = $row['edition'];

                // 출판사명 → publisher_id (Publisher 없으면 자동 생성)
                if (! empty($row['publisher_name'])) {
                    $pub = Publisher::firstOrCreate(
                        ['name' => $row['publisher_name']],
                        ['sort_order' => 999, 'is_active' => true]
                    );
                    $payload['publisher_id'] = $pub->id;
                }

                $book = Book::where('isbn', $row['isbn'])->first();
                if ($book) {
                    if ($mode === 'skip_existing') { continue; }
                    // 업데이트 — payload에 있는 컬럼만 갱신 (엑셀에 없는 컬럼은 DB 값 보존)
                    $book->update($payload);
                    $updated++;
                } else {
                    // 신규 생성 — 필수 기본값 + ISBN + source 추가
                    $createPayload = $payload + [
                        'isbn'        => $row['isbn'],
                        'status_code' => 'selling',
                        'source'      => 'excel',
                    ];
                    // 이중 안전: status_code가 비어있으면 기본값 강제 (NOT NULL)
                    if (empty($createPayload['status_code'])) {
                        $createPayload['status_code'] = 'selling';
                    }
                    if (empty($createPayload['title'])) {
                        $createPayload['title'] = $row['title'] ?? '(제목 없음)';
                    }
                    $book = Book::create($createPayload);
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
            }
            // 매칭 안 되는 값은 무시 — 행 에러로 처리하지 않고 그 항목만 분류에서 제외
            // (도서는 정상 등록되며, 필요 시 관리자가 코드테이블 추가 후 개별 수정)
        }
        return array_unique($codes);
    }

    /** ISBN10 → ISBN13 표준 변환 (978 prefix + EAN-13 체크섬 계산) */
    private function convertIsbn10To13(string $isbn10): string
    {
        // 첫 9자리 + '978' prefix
        $body = '978' . substr($isbn10, 0, 9);
        if (strlen($body) !== 12 || ! ctype_digit($body)) return $isbn10;
        // EAN-13 체크섬: 짝수 자리 ×1, 홀수 자리 ×3 (1-indexed)
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += ((int) $body[$i]) * (($i % 2 === 0) ? 1 : 3);
        }
        $check = (10 - ($sum % 10)) % 10;
        return $body . $check;
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
