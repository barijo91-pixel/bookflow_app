<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\ReturnService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * 물류센터 반품 회수 요청 엑셀 — 반품 목록에서 고른 건을 내보낸다.
 *
 * 반품기사가 방문해서 걷어오는 문서라, 출고 엑셀과 달리 **회수지**가 중심이다.
 *
 * 시트 3장:
 *  - 회수목록:   회수지로 묶은 픽업 목록. 한 회수지가 여러 반품에 걸쳐 있어도 한 덩어리
 *  - 회수지목록: 1행 = 방문지. 기사 배차용 (몇 군데 가서 몇 권 걷나)
 *  - 반품요약:   1행 = 반품. 건수·금액 대조용 (내부)
 *
 * 회수지는 이렇게 갈린다.
 *  - 학부모가 신청한 반품(결제요청에 연결): 학부모 집. 주소가 없으면 학원으로 돌리고 비고에 표시
 *  - 그 외(학원·영업자·총판 접수): 학원 주소
 */
class ReturnExportController extends Controller
{
    /** 회수를 보낼 수 있는 상태 — 확정된 반품만 (접수 대기·반려는 제외) */
    public const EXPORTABLE_STATUS = ['confirmed'];

    public function export(Request $request)
    {
        $user = Auth::user();
        // 출고 엑셀과 같은 기준 — 물류와 주고받는 문서는 총판 역할
        if (! $user || $user->role_code !== 'distributor') {
            abort(403, '총판만 반품 회수 엑셀을 사용할 수 있습니다.');
        }

        $ids = $request->input('return_ids', []);
        if (is_string($ids)) $ids = explode(',', $ids);
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $ids))));

        if (! $ids) {
            return back()->with('error', '내보낼 반품을 선택해 주세요.');
        }
        if (count($ids) > 500) {
            return back()->with('error', '한 번에 500건까지 내보낼 수 있습니다.');
        }

        // 본인 스코프 + 확정된 것만. 스코프 밖 id 가 섞여 들어와도 여기서 걸러진다.
        $rows = DB::table('returns as r')
            ->leftJoin('vendors as v', 'v.id', '=', 'r.vendor_id')
            ->leftJoin('users as ag', 'ag.id', '=', 'r.agent_user_id')
            ->leftJoin('payment_requests as pr', 'pr.id', '=', 'r.payment_request_id')
            ->leftJoin('parents as p', 'p.id', '=', 'pr.parent_id')
            ->leftJoin('orders as o', 'o.id', '=', 'r.order_id')
            ->whereIn('r.id', $ids)
            ->where('r.distributor_user_id', $user->id)
            ->whereNull('r.deleted_at')
            ->orderBy('r.id')
            ->get([
                'r.id', 'r.return_no', 'r.status', 'r.reason_code', 'r.reason_text',
                'r.total_qty', 'r.total_amount', 'r.refund_status', 'r.refund_amount',
                'r.requested_at', 'r.confirmed_at', 'r.order_id',
                'v.name as vendor_name', 'v.tel as vendor_tel', 'v.mobile as vendor_mobile',
                'v.address as vendor_address', 'v.address_detail as vendor_address_detail',
                'ag.name as agent_name',
                'o.order_no',
                'pr.parent_name', 'pr.parent_phone', 'pr.student_name',
                'p.address as parent_address', 'p.address_detail as parent_address_detail',
            ]);

        $skipped = count($ids) - $rows->count();          // 스코프 밖이라 빠진 건
        $exclude = $rows->reject(fn ($r) => in_array($r->status, self::EXPORTABLE_STATUS, true));
        $returns = $rows->filter(fn ($r) => in_array($r->status, self::EXPORTABLE_STATUS, true))->values();

        if ($returns->isEmpty()) {
            return back()->with('error', '내보낼 수 있는 반품이 없습니다. 확정된 반품만 회수 요청할 수 있습니다.');
        }

        $items = DB::table('return_items')
            ->whereIn('return_id', $returns->pluck('id'))
            ->get(['return_id', 'title_snapshot', 'isbn_snapshot', 'qty', 'unit_price'])
            ->groupBy('return_id');

        // ── 회수지로 묶기
        $pickups = [];
        foreach ($returns as $r) {
            $rItems = $items[$r->id] ?? collect();
            if ($rItems->isEmpty()) continue;

            $note = '';
            // 학부모 신청 건은 학부모 집에서 걷는다. 주소가 없으면 학원으로 돌린다.
            if ($r->parent_name && $r->parent_address) {
                $to = [
                    $r->parent_name . ($r->student_name ? " ({$r->student_name} 학생)" : ''),
                    (string) $r->parent_phone,
                    (string) $r->parent_address,
                    (string) $r->parent_address_detail,
                ];
            } else {
                if ($r->parent_name && ! $r->parent_address) {
                    $note = '학부모 주소 없음 — 학원으로 회수';
                }
                $to = [
                    $r->vendor_name,
                    (string) ($r->vendor_tel ?: $r->vendor_mobile),
                    (string) $r->vendor_address,
                    (string) $r->vendor_address_detail,
                ];
            }
            if (! $to[2]) {
                $note = trim($note . ' 주소 없음 — 확인 필요');
            }

            $key = implode('|', $to);
            if (! isset($pickups[$key])) $pickups[$key] = ['to' => $to, 'rows' => []];

            foreach ($rItems as $it) {
                $pickups[$key]['rows'][] = [
                    $r->return_no,
                    (string) $r->order_no,
                    $r->confirmed_at ? substr((string) $r->confirmed_at, 0, 10) : '',
                    $r->vendor_name,
                    $it->title_snapshot,
                    (string) $it->isbn_snapshot,
                    (int) $it->qty,
                    ReturnService::REASONS[$r->reason_code] ?? $r->reason_code,
                    trim($note . ' ' . (string) $r->reason_text),
                ];
            }
        }
        $pickups = array_values($pickups);

        $spreadsheet = new Spreadsheet();

        // ── 시트 1: 회수목록 — 회수지로 묶고, 덩어리마다 회수번호를 매긴다
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('회수목록');
        $sheet->fromArray(['회수번호', '반품번호', '주문번호', '확정일', '학원',
                           '방문지', '연락처', '주소', '상세주소',
                           '교재명', 'ISBN', '수량', '사유', '비고'], null, 'A1');

        $r = 2; $no = 0;
        foreach ($pickups as $p) {
            $no++;
            foreach ($p['rows'] as $i => $row) {
                // 회수번호·방문지·주소는 덩어리 첫 줄에만 — 기사가 방문 단위를 눈으로 세게 한다
                $first = $i === 0;
                $this->writeRow($sheet, $r++, array_merge(
                    [$first ? $no : ''],
                    array_slice($row, 0, 4),                    // 반품번호·주문번호·확정일·학원
                    $first ? $p['to'] : ['', '', '', ''],       // 방문지·연락처·주소·상세
                    array_slice($row, 4)                        // 교재명·ISBN·수량·사유·비고
                ));
            }
        }
        $this->styleSheet($sheet, $r - 1);

        // ── 시트 2: 회수지목록 — 기사 배차용. 몇 군데 가서 몇 권 걷나
        $dest = $spreadsheet->createSheet();
        $dest->setTitle('회수지목록');
        $dest->fromArray(['회수번호', '방문지', '연락처', '주소', '상세주소',
                          '교재 종수', '총수량', '반품번호', '비고'], null, 'A1');
        $dr = 2; $no = 0;
        foreach ($pickups as $p) {
            $no++;
            $qty    = array_sum(array_map(fn ($x) => (int) $x[6], $p['rows']));
            $notes  = array_values(array_unique(array_filter(array_map(fn ($x) => trim((string) $x[8]), $p['rows']))));
            $retNos = implode(', ', array_values(array_unique(array_map(fn ($x) => (string) $x[0], $p['rows']))));
            $dest->fromArray(array_merge([$no], $p['to'], [
                count($p['rows']), $qty, $retNos, implode(' / ', $notes),
            ]), null, 'A' . $dr);
            $dest->getCell('C' . $dr)->setValueExplicit((string) $p['to'][1], DataType::TYPE_STRING);
            $dest->getCell('H' . $dr)->setValueExplicit($retNos, DataType::TYPE_STRING);
            $dr++;
        }
        $this->styleSheet($dest, $dr - 1);

        // ── 시트 3: 반품요약
        $sum = $spreadsheet->createSheet();
        $sum->setTitle('반품요약');
        $sum->fromArray(['반품번호', '주문번호', '접수일', '확정일', '학원', '영업자',
                         '사유', '상세사유', '품목수', '총수량', '반품금액', '환불'], null, 'A1');
        $sr = 2;
        $refundNames = ['none' => '해당 없음', 'pending' => '대기', 'partial' => '일부', 'done' => '완료', 'failed' => '실패'];
        foreach ($returns as $ret) {
            $rItems = $items[$ret->id] ?? collect();
            $sum->fromArray([
                $ret->return_no,
                (string) $ret->order_no,
                substr((string) $ret->requested_at, 0, 10),
                $ret->confirmed_at ? substr((string) $ret->confirmed_at, 0, 10) : '',
                $ret->vendor_name,
                $ret->agent_name ?? '',
                ReturnService::REASONS[$ret->reason_code] ?? $ret->reason_code,
                (string) $ret->reason_text,
                $rItems->count(), (int) $rItems->sum('qty'), (int) $ret->total_amount,
                $refundNames[$ret->refund_status] ?? $ret->refund_status,
            ], null, 'A' . $sr);
            foreach (['A', 'B'] as $col) {
                $sum->getCell($col . $sr)->setValueExplicit(
                    (string) $sum->getCell($col . $sr)->getValue(), DataType::TYPE_STRING
                );
            }
            $sr++;
        }
        $this->styleSheet($sum, $sr - 1);

        $spreadsheet->setActiveSheetIndex(0);

        AuditLog::log('returns', 0, 'logistics_export', null, [
            'count' => $returns->count(), 'return_nos' => $returns->pluck('return_no')->take(50)->all(),
            'skipped' => $skipped, 'not_confirmed' => $exclude->count(),
        ]);

        $writer   = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $filename = '반품회수요청_' . now()->format('Ymd_His') . '_' . $returns->count() . '건.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'        => 'no-cache',
        ]);
    }

    /** 한 줄 쓰기 — 반품번호·주문번호·연락처·ISBN 은 엑셀이 숫자로 바꾸지 않게 텍스트 고정 */
    private function writeRow($sheet, int $row, array $values): void
    {
        $sheet->fromArray($values, null, 'A' . $row);
        foreach (['B', 'C', 'G', 'K'] as $col) {   // 반품번호, 주문번호, 연락처, ISBN
            $sheet->getCell($col . $row)->setValueExplicit(
                (string) $sheet->getCell($col . $row)->getValue(), DataType::TYPE_STRING
            );
        }
    }

    private function styleSheet($sheet, int $lastRow): void
    {
        $lastCol = $sheet->getHighestColumn();
        $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastCol}1")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8EEF7');
        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->freezePane('A2');
        if ($lastRow >= 2) {
            $sheet->setAutoFilter("A1:{$lastCol}{$lastRow}");
        }
    }
}
