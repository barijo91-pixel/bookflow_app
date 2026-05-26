<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class StudentImportService
{
    /** 엑셀 헤더 → 내부 필드 매핑 */
    public const COLUMN_MAP = [
        '학생이름'        => 'name',
        '학생명'          => 'name',
        '이름'            => 'name',
        '학년'            => 'grade_code',
        '학부모이름'      => 'parent_name',
        '학부모명'        => 'parent_name',
        '학부모휴대폰'    => 'parent_phone',
        '학부모폰'        => 'parent_phone',
        '학부모전화'      => 'parent_phone',
        '학부모이메일'    => 'parent_email',
        '메모'            => 'memo',
        '비고'            => 'memo',
    ];

    public const TEMPLATE_HEADERS = [
        '학생이름', '학년', '학부모이름', '학부모휴대폰', '학부모이메일', '메모',
    ];

    /** 학년 코드 매핑 (한글명 → code) */
    private array $gradeMap = [];

    public function __construct()
    {
        $this->gradeMap = DB::table('codes')
            ->where('group_code', 'grade')
            ->pluck('code', 'name')
            ->toArray();
    }

    /** 빈 템플릿 Spreadsheet 객체 반환 (streamDownload용) */
    public function buildTemplate(): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('학생 일괄 등록');

        foreach (self::TEMPLATE_HEADERS as $i => $h) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue($col.'1', $h);
        }

        // 예시 행
        $samples = [
            ['김민준', '초3', '김아빠', '01012340001', null, '영어 회화반'],
            ['이서윤', '초3', '이엄마', '01012340002', 'mom@example.com', null],
            ['박지호', '초4', '박아빠', '01012340003', null, '수학 보충'],
        ];
        foreach ($samples as $rowIdx => $sample) {
            foreach ($sample as $colIdx => $val) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 1);
                $sheet->setCellValue($col.($rowIdx + 2), $val);
            }
        }

        // 헤더 스타일
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count(self::TEMPLATE_HEADERS));
        $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastCol}1")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('EAF0FA');
        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // 안내 시트
        $info = $spreadsheet->createSheet();
        $info->setTitle('안내');
        $info->setCellValue('A1', '학생 일괄 등록 가이드');
        $info->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $info->setCellValue('A3', '필수 컬럼: 학생이름, 학부모이름, 학부모휴대폰');
        $info->setCellValue('A4', '선택 컬럼: 학년, 학부모이메일, 메모');
        $info->setCellValue('A6', '학년 예시: 초1, 초2, 초3, 초4, 초5, 초6, 중1, 중2, 중3, 고1, 고2, 고3');
        $info->setCellValue('A7', '학년은 한글명 또는 code 어느 쪽이든 입력 가능');
        $info->setCellValue('A9', '학부모 휴대폰이 같으면 한 학부모로 인식 (형제·자매 자동 연결)');
        $info->setCellValue('A10', '한 행에 학생 1명. 형제·자매도 행을 따로 만드세요 (학부모는 자동 묶임)');
        $info->getColumnDimension('A')->setAutoSize(true);

        $spreadsheet->setActiveSheetIndex(0);
        return $spreadsheet;
    }

    /**
     * 엑셀 파일 파싱 + 검증
     * @return array{rows:array, errors:array, total:int}
     */
    public function parse(string $filePath, int $classId, int $vendorId): array
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

        if (! isset($colIdx['name']) || ! isset($colIdx['parent_name']) || ! isset($colIdx['parent_phone'])) {
            return ['rows' => [], 'errors' => [
                ['row' => 1, 'msg' => '필수 헤더 누락: 학생이름, 학부모이름, 학부모휴대폰. 헤더: '.implode('|', $header)]
            ], 'total' => 0];
        }

        // 이미 같은 학급에 있는 학생 이름 조회 (중복 방지)
        $existingNames = DB::table('students')
            ->where('class_id', $classId)
            ->whereNull('deleted_at')
            ->pluck('name')
            ->map(fn ($n) => trim($n))
            ->toArray();

        $rows = [];
        $errors = [];
        $rowNumber = 1;
        $seenInFile = []; // 엑셀 내 (이름,부모폰) 중복 검출
        foreach ($data as $r) {
            $rowNumber++;
            if (count(array_filter($r, fn ($v) => trim((string) $v) !== '')) === 0) continue;

            $row = [];
            foreach ($colIdx as $field => $i) {
                $row[$field] = isset($r[$i]) ? (is_string($r[$i]) ? trim($r[$i]) : $r[$i]) : null;
            }

            $rowErrors = [];

            // 학생 이름
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '' || mb_strlen($name) > 80) $rowErrors[] = '학생이름 누락/길이 초과(80자)';
            $row['name'] = $name;

            // 학부모 이름
            $pname = trim((string) ($row['parent_name'] ?? ''));
            if ($pname === '' || mb_strlen($pname) > 80) $rowErrors[] = '학부모이름 누락/길이 초과(80자)';
            $row['parent_name'] = $pname;

            // 학부모 휴대폰
            $pphone = preg_replace('/[^0-9]/', '', (string) ($row['parent_phone'] ?? ''));
            if (! $pphone || strlen($pphone) < 9 || strlen($pphone) > 13) $rowErrors[] = '학부모휴대폰 형식 오류';
            $row['parent_phone'] = $pphone;

            // 학부모 이메일
            if (! empty($row['parent_email']) && ! filter_var($row['parent_email'], FILTER_VALIDATE_EMAIL)) {
                $rowErrors[] = '학부모이메일 형식 오류';
            }

            // 학년 (한글명 또는 code 모두 허용)
            $gradeInput = trim((string) ($row['grade_code'] ?? ''));
            if ($gradeInput !== '') {
                if (isset($this->gradeMap[$gradeInput])) {
                    $row['grade_code'] = $this->gradeMap[$gradeInput]; // 한글명 → code
                } elseif (in_array($gradeInput, $this->gradeMap, true)) {
                    $row['grade_code'] = $gradeInput; // 이미 code
                } else {
                    $rowErrors[] = "학년 '{$gradeInput}' 매칭 안됨 (예: 초1, 초2, …)";
                }
            } else {
                $row['grade_code'] = null;
            }

            // 메모 길이
            if (! empty($row['memo']) && mb_strlen((string) $row['memo']) > 500) {
                $rowErrors[] = '메모 길이 초과(500자)';
            }

            // 중복 (학급 내 동명이인 + 같은 부모 → 같은 학생일 가능성)
            $key = $name.'|'.$pphone;
            if ($name !== '' && in_array($name, $existingNames, true)) {
                $rowErrors[] = "학급에 동일 이름 '{$name}' 이미 존재";
            } elseif (isset($seenInFile[$key])) {
                $rowErrors[] = "엑셀 내 중복: {$name} / {$pphone}";
            } else {
                $seenInFile[$key] = true;
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
     * 실제 import 수행
     * @return array{success:int, failed:int, errors:array}
     */
    public function import(array $rows, int $classId, int $vendorId): array
    {
        $success = 0; $failed = 0; $errors = [];

        foreach ($rows as $row) {
            if (! empty($row['_errors'])) { $failed++; continue; }

            try {
                DB::transaction(function () use ($row, $classId, $vendorId) {
                    // 부모: 같은 전화 → 재사용
                    $parentId = DB::table('parents')
                        ->where('phone', $row['parent_phone'])
                        ->whereNull('deleted_at')
                        ->value('id');
                    if (! $parentId) {
                        $parentId = DB::table('parents')->insertGetId([
                            'name'       => $row['parent_name'],
                            'phone'      => $row['parent_phone'],
                            'email'      => $row['parent_email'] ?? null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    } else {
                        // 기존 부모인데 이름/이메일이 다르면 기존값 유지 (혼선 방지)
                        if (! empty($row['parent_email'])) {
                            DB::table('parents')->where('id', $parentId)->whereNull('email')->update([
                                'email' => $row['parent_email'], 'updated_at' => now(),
                            ]);
                        }
                    }

                    DB::table('students')->insert([
                        'vendor_id'  => $vendorId,
                        'class_id'   => $classId,
                        'parent_id'  => $parentId,
                        'name'       => $row['name'],
                        'grade_code' => $row['grade_code'] ?? null,
                        'memo'       => $row['memo'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                });
                $success++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = ['row' => $row['_row'] ?? '?', 'msg' => $e->getMessage()];
            }
        }

        return ['success' => $success, 'failed' => $failed, 'errors' => $errors];
    }
}
