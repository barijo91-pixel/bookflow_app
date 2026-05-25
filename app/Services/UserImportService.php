<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\IOFactory;

class UserImportService
{
    /** 엑셀 헤더 컬럼명 → 내부 필드 */
    public const COLUMN_MAP = [
        '아이디'        => 'login_id',
        '로그인ID'      => 'login_id',
        'login_id'     => 'login_id',
        '이름'          => 'name',
        '성명'          => 'name',
        '휴대폰'        => 'phone',
        '전화'          => 'phone',
        'phone'        => 'phone',
        '이메일'        => 'email',
        'email'        => 'email',
        '역할'          => 'role_code',
        'role'         => 'role_code',
        '시도'          => 'sido',
        '시군구'        => 'sigungu',
        '주소'          => 'address',
        '상세주소'      => 'address_detail',
        '초기비밀번호'   => 'password',
        '비밀번호'      => 'password',
    ];

    public const TEMPLATE_HEADERS = [
        '아이디', '이름', '휴대폰', '이메일', '역할', '시도', '시군구', '주소', '상세주소', '초기비밀번호',
    ];

    /** 역할 한글명 → code */
    private array $roleMap = [
        '총판'        => 'distributor',
        'distributor' => 'distributor',
        '영업자'      => 'agent',
        'agent'      => 'agent',
        '학원'        => 'academy',
        '학원담당'    => 'academy',
        'academy'    => 'academy',
    ];

    /** 시도/시군구 → region_id 캐시 */
    private array $regionCache = [];

    public function __construct()
    {
        $regions = DB::table('regions')->get(['id', 'name', 'parent_id', 'level']);
        $sidos = $regions->where('level', 'sido')->keyBy('id');
        foreach ($regions as $r) {
            if ($r->level === 'sigungu') {
                $sidoName = $sidos->get($r->parent_id)?->name;
                $this->regionCache[$sidoName.'|'.$r->name] = $r->id;
            } elseif ($r->level === 'sido') {
                $this->regionCache[$r->name.'|'] = $r->id; // 시도만 입력 시
            }
        }
    }

    /**
     * 엑셀 파일 파싱
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

        if (! isset($colIdx['login_id']) || ! isset($colIdx['name']) || ! isset($colIdx['phone']) || ! isset($colIdx['role_code'])) {
            return ['rows' => [], 'errors' => [
                ['row' => 1, 'msg' => '필수 헤더 누락: 아이디, 이름, 휴대폰, 역할 필요. 헤더: '.implode('|', $header)]
            ], 'total' => 0];
        }

        $existingLoginIds = DB::table('users')->whereIn('login_id',
            array_filter(array_column($data, $colIdx['login_id'])))->pluck('login_id')->toArray();
        $existingLoginIdsLower = array_map('strtolower', $existingLoginIds);

        $rows = [];
        $errors = [];
        $rowNumber = 1;
        $seenLoginIds = []; // 엑셀 내 중복 검출용
        foreach ($data as $r) {
            $rowNumber++;
            if (count(array_filter($r, fn ($v) => trim((string) $v) !== '')) === 0) continue;

            $row = [];
            foreach ($colIdx as $field => $i) {
                $row[$field] = isset($r[$i]) ? (is_string($r[$i]) ? trim($r[$i]) : $r[$i]) : null;
            }

            // 검증
            $rowErrors = [];

            // 아이디
            $loginId = trim((string) ($row['login_id'] ?? ''));
            if (! preg_match('/^[a-zA-Z0-9]{6,50}$/', $loginId)) {
                $rowErrors[] = '아이디는 영문+숫자 6~50자';
            } else {
                $lower = strtolower($loginId);
                if (in_array($lower, $existingLoginIdsLower, true)) {
                    $rowErrors[] = "아이디 '{$loginId}' 이미 존재";
                } elseif (in_array($lower, $seenLoginIds, true)) {
                    $rowErrors[] = "아이디 '{$loginId}' 엑셀 내 중복";
                } else {
                    $seenLoginIds[] = $lower;
                }
            }
            $row['login_id'] = $loginId;

            // 이름
            if (empty($row['name'])) $rowErrors[] = '이름 없음';

            // 휴대폰
            $phone = preg_replace('/[^0-9]/', '', (string) ($row['phone'] ?? ''));
            if (! $phone || strlen($phone) < 9 || strlen($phone) > 13) $rowErrors[] = '휴대폰 형식 오류';
            $row['phone'] = $phone;

            // 역할
            $roleInput = trim((string) ($row['role_code'] ?? ''));
            if (isset($this->roleMap[$roleInput])) {
                $row['role_code'] = $this->roleMap[$roleInput];
            } else {
                $rowErrors[] = "역할 '{$roleInput}' 매칭 안됨 (총판/영업자/학원)";
            }
            // admin 차단
            if (($row['role_code'] ?? '') === 'admin') $rowErrors[] = '관리자는 일괄 등록 불가';

            // 이메일 (선택)
            if (! empty($row['email']) && ! filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                $rowErrors[] = '이메일 형식 오류';
            }

            // 지역
            $sido = trim((string) ($row['sido'] ?? ''));
            $sigungu = trim((string) ($row['sigungu'] ?? ''));
            $row['region_id'] = null;
            if ($sido || $sigungu) {
                $key = $sido.'|'.$sigungu;
                if (isset($this->regionCache[$key])) {
                    $row['region_id'] = $this->regionCache[$key];
                } else {
                    $rowErrors[] = "지역 '{$sido} {$sigungu}' 찾을 수 없음";
                }
            }

            // 초기 비번 (없으면 자동 생성)
            $pw = trim((string) ($row['password'] ?? ''));
            if (! $pw) {
                $pw = $this->genPassword(8);
            } elseif (strlen($pw) < 8) {
                $rowErrors[] = '초기비밀번호는 8자 이상';
            }
            $row['password'] = $pw;

            $row['_row'] = $rowNumber;
            $row['_errors'] = $rowErrors;

            if ($rowErrors) {
                $errors[] = ['row' => $rowNumber, 'msg' => implode(', ', $rowErrors), 'data' => $row];
            }
            $rows[] = $row;
        }

        return ['rows' => $rows, 'errors' => $errors, 'total' => count($rows)];
    }

    /**
     * 실제 import 수행 (검증 통과 행만)
     * @return array{success: int, failed: int, errors: array, created_users: array}
     */
    public function import(array $rows): array
    {
        $success = 0; $failed = 0; $errors = [];
        $createdUsers = []; // [['login_id', 'password', 'role'], ...] 로 반환 (관리자가 받아 사용자에게 전달)
        $approvedBy = auth()->id();

        foreach ($rows as $row) {
            if (! empty($row['_errors'])) { $failed++; continue; }

            try {
                $plainPw = $row['password'];
                $user = User::create([
                    'login_id'    => $row['login_id'],
                    'email'       => $row['email'] ?? null,
                    'name'        => $row['name'],
                    'phone'       => $row['phone'],
                    'password'    => $plainPw, // model casts hashed
                    'password_change_required' => true,
                    'role_code'   => $row['role_code'],
                    'status_code' => 'active', // 관리자가 일괄 등록 → 자동 active
                    'region_id'   => $row['region_id'] ?? null,
                    'address'     => $row['address'] ?? null,
                    'address_detail' => $row['address_detail'] ?? null,
                    'approved_by' => $approvedBy,
                    'approved_at' => now(),
                ]);

                $createdUsers[] = [
                    'login_id' => $user->login_id,
                    'name'     => $user->name,
                    'phone'    => $user->phone,
                    'role'     => $user->role_code,
                    'password' => $plainPw, // 평문 — 1회만 화면에 노출
                ];
                $success++;

            } catch (\Throwable $e) {
                $failed++;
                $errors[] = ['row' => $row['_row'] ?? '?', 'msg' => $e->getMessage()];
            }
        }
        return ['success' => $success, 'failed' => $failed, 'errors' => $errors, 'created_users' => $createdUsers];
    }

    /** 빈 템플릿 엑셀 생성 (Spreadsheet 객체 반환 → 컨트롤러에서 stream) */
    public function buildTemplate(): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('사용자 일괄 등록');

        foreach (self::TEMPLATE_HEADERS as $i => $h) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue($col.'1', $h);
        }

        // 예시 행 3개 (총판/영업자/학원)
        $samples = [
            ['kimagent01', '김영업', '01012340001', null, '영업자', '서울특별시', '강남구', '테헤란로 1', '101호', ''],
            ['leeacademy01', '이원장', '01012340002', null, '학원', '서울특별시', '서초구', '서초대로 1', null, ''],
            ['parkdist01', '박총판', '01012340003', null, '총판', '경기도', '성남시 분당구', '판교로 1', null, 'Park2026'],
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
        $info->setCellValue('A1', '사용자 일괄 등록 가이드');
        $info->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $info->setCellValue('A3', '필수 컬럼: 아이디, 이름, 휴대폰, 역할');
        $info->setCellValue('A4', '아이디: 영문+숫자 6~50자 (대소문자 무관, 유일)');
        $info->setCellValue('A5', '휴대폰: 숫자만 (10~13자), 자동으로 하이픈 제거');
        $info->setCellValue('A6', '역할: 총판 / 영업자 / 학원 (관리자는 일괄 등록 불가)');
        $info->setCellValue('A8', '선택 컬럼:');
        $info->setCellValue('A9', '  이메일 — 알림 수신용');
        $info->setCellValue('A10', '  시도/시군구 — DB의 지역명과 일치해야 함');
        $info->setCellValue('A11', '  주소/상세주소 — 자유 입력');
        $info->setCellValue('A12', '  초기비밀번호 — 비어있으면 자동 생성 (8자), 등록 후 1회 화면 노출');
        $info->setCellValue('A14', '등록된 사용자는:');
        $info->setCellValue('A15', '  - 자동으로 status=active');
        $info->setCellValue('A16', '  - 첫 로그인 시 비밀번호 변경 강제');
        $info->getColumnDimension('A')->setAutoSize(true);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /** 후방 호환: 기존 호출 시 임시 파일 경로 반환 */
    public function generateTemplate(): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'user_tpl_').'.xlsx';
        $writer = IOFactory::createWriter($this->buildTemplate(), 'Xlsx');
        $writer->save($tmp);
        return $tmp;
    }

    private function genPassword(int $length = 8): string
    {
        $letters = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
        $digits  = '23456789';
        $half = (int) floor($length / 2);
        $pw = substr(str_shuffle(str_repeat($letters, 4)), 0, $half)
            . substr(str_shuffle(str_repeat($digits, 4)), 0, $length - $half);
        return str_shuffle($pw);
    }
}
