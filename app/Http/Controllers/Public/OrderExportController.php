<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * 물류센터 출고 요청 엑셀 — 주문 목록에서 고른 주문을 내보낸다.
 *
 * 시트 3장:
 *  - 출고목록:   배송지로 묶은 피킹 목록. 한 배송지가 여러 주문에 걸쳐 있어도 한 덩어리
 *  - 배송지목록: 1행 = 배송지. 송장 몇 장인지·배송지마다 몇 권인지
 *  - 주문요약:   1행 = 주문. 건수·금액 대조용 (내부)
 *
 * 배송지는 수령형태(ship_to_type)에 따라 갈린다.
 *  - vendor(학원 일괄)·도매: 학원 주소 한 곳으로 품목별 총 수량
 *  - parent(학부모 개별): 주문에 걸린 학생마다 한 배송지. 학생별 수량이 따로
 *    기록되지 않으므로 균등 배분하고, 나누어떨어지지 않으면 비고에 표시한다.
 */
class OrderExportController extends Controller
{
    /** 물류로 넘길 수 있는 상태 — 확정 이후 (접수 대기·취소는 제외) */
    public const EXPORTABLE_STATUS = ['confirmed', 'accepted', 'shipped', 'in_transit', 'completed'];

    public function export(Request $request)
    {
        $user = Auth::user();
        // 물류센터 출고 엑셀은 총판 역할 — 영업자는 제외
        if (! $user || $user->role_code !== 'distributor') {
            abort(403, '총판만 물류 출고 엑셀을 사용할 수 있습니다.');
        }

        $ids = $request->input('order_ids', []);
        if (is_string($ids)) $ids = explode(',', $ids);
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $ids))));

        if (! $ids) {
            return back()->with('error', '내보낼 주문을 선택해 주세요.');
        }
        if (count($ids) > 500) {
            return back()->with('error', '한 번에 500건까지 내보낼 수 있습니다.');
        }

        // 본인 스코프 + 확정 이후만. 스코프 밖 id 가 섞여 들어와도 여기서 걸러진다.
        $q = DB::table('orders as o')
            ->leftJoin('vendors as v', 'v.id', '=', 'o.vendor_id')
            ->leftJoin('academy_classes as ac', 'ac.id', '=', 'o.class_id')
            ->leftJoin('users as ag', 'ag.id', '=', 'o.agent_user_id')
            ->whereIn('o.id', $ids)
            ->whereNull('o.deleted_at');

        if ($user->role_code === 'agent') {
            $q->where('o.agent_user_id', $user->id);
        } else {
            $q->where('o.distributor_user_id', $user->id);
        }

        $orders = $q->orderBy('o.id')->get([
            'o.id', 'o.order_no', 'o.status_code', 'o.requested_at', 'o.confirmed_at', 'o.created_at',
            'o.ship_to_type', 'o.ship_to_address', 'o.ship_to_address_detail', 'o.ship_to_contact',
            'o.delivery_type', 'o.delivery_memo', 'o.total_amount',
            'v.name as vendor_name', 'v.trade_type', 'v.address as vendor_address',
            'v.address_detail as vendor_address_detail', 'v.mobile as vendor_mobile', 'v.tel as vendor_tel',
            'v.owner_name as vendor_owner',
            'ac.name as class_name', 'ag.name as agent_name',
        ]);

        $skipped  = count($ids) - $orders->count();
        $exclude  = $orders->filter(fn ($o) => ! in_array($o->status_code, self::EXPORTABLE_STATUS, true));
        $orders   = $orders->filter(fn ($o) => in_array($o->status_code, self::EXPORTABLE_STATUS, true))->values();

        if ($orders->isEmpty()) {
            return back()->with('error', '내보낼 수 있는 주문이 없습니다. 확정 이후의 주문만 물류로 넘길 수 있습니다.');
        }

        $orderIds = $orders->pluck('id')->all();

        $items = DB::table('order_items as oi')
            ->leftJoin('books as b', 'b.id', '=', 'oi.book_id')
            ->whereIn('oi.order_id', $orderIds)
            ->orderBy('oi.id')
            ->get(['oi.order_id', 'oi.title_snapshot', 'oi.isbn_snapshot', 'oi.qty', 'oi.unit_price',
                   'b.publisher_code', 'b.title as book_title'])
            ->groupBy('order_id');

        // 학부모 개별배송용 — 주문에 걸린 학생 + 학부모 주소
        $students = DB::table('order_students as os')
            ->leftJoin('students as s', 's.id', '=', 'os.student_id')
            ->leftJoin('parents as p', 'p.id', '=', 's.parent_id')
            ->whereIn('os.order_id', $orderIds)
            ->orderBy('os.id')
            ->get(['os.order_id', 'os.student_name', 'os.parent_name',
                   'p.phone as parent_phone', 'p.address as parent_address',
                   'p.address_detail as parent_address_detail'])
            ->groupBy('order_id');

        // ── 배송지별로 모은다. 물류는 주문이 아니라 "어디로 몇 박스"로 일하므로
        //    같은 주소로 갈 것은 여러 주문에 걸쳐 있어도 한 덩어리로 붙인다.
        $deliveries = [];   // key => ['to' => [받는분,연락처,주소,상세], 'rows' => [...]]
        $addRow = function (array $to, array $row) use (&$deliveries) {
            $key = implode('|', array_map(fn ($v) => preg_replace('/\s+/u', '', (string) $v), $to));
            if (! isset($deliveries[$key])) $deliveries[$key] = ['to' => $to, 'rows' => []];
            $deliveries[$key]['rows'][] = $row;
        };

        foreach ($orders as $o) {
            $oItems = $items[$o->id] ?? collect();
            if ($oItems->isEmpty()) continue;

            $isParcel  = ($o->delivery_type ?? 'parcel') !== 'direct';
            $isWhole   = ($o->trade_type ?? 'retail') === 'wholesale';
            // 도매는 수령형태와 무관하게 학원으로 나간다 (학부모 개별 개념이 없다)
            $toVendor  = $isWhole || ($o->ship_to_type ?? 'parent') === 'vendor';
            $head      = [$o->order_no,
                          substr((string) ($o->requested_at ?? $o->created_at), 0, 10),
                          $o->confirmed_at ? substr((string) $o->confirmed_at, 0, 10) : '',
                          $o->vendor_name];
            $tail      = [$isParcel ? '택배' : '직접배송(화물·용달)', (string) $o->delivery_memo];

            if ($toVendor) {
                // 학원 한 곳으로 — 주문에 배송지가 따로 잡혀 있으면 그게 우선
                $addr   = $o->ship_to_address ?: $o->vendor_address;
                $detail = $o->ship_to_address ? $o->ship_to_address_detail : $o->vendor_address_detail;
                $phone  = $o->ship_to_contact ?: ($o->vendor_mobile ?: $o->vendor_tel);
                $to     = [$o->vendor_name, (string) $phone, (string) $addr, (string) $detail];
                $note   = $addr ? '' : '주소 없음 — 확인 필요';

                foreach ($oItems as $it) {
                    $addRow($to, array_merge($head, [
                        $it->title_snapshot, (string) $it->isbn_snapshot, (int) $it->qty,
                    ], $tail, [$note]));
                }
            } else {
                $list = $students[$o->id] ?? collect();
                if ($list->isEmpty()) {
                    // 학부모 개별인데 학생이 안 걸린 주문 — 물류가 보낼 곳이 없다. 눈에 띄게 남긴다.
                    foreach ($oItems as $it) {
                        $addRow(['', '', '', ''], array_merge($head, [
                            $it->title_snapshot, (string) $it->isbn_snapshot, (int) $it->qty,
                        ], $tail, ['대상 학생 없음 — 배송지 확인 필요']));
                    }
                    continue;
                }

                $n = $list->count();
                foreach ($list as $st) {
                    $to = [(string) ($st->parent_name ?: $st->student_name), (string) $st->parent_phone,
                           (string) $st->parent_address, (string) $st->parent_address_detail];
                    foreach ($oItems as $it) {
                        // 학생별 수량이 따로 기록되지 않아 균등 배분한다
                        $per  = intdiv((int) $it->qty, $n);
                        $note = ((int) $it->qty % $n === 0)
                            ? "학생 {$n}명 균등배분"
                            : "학생 {$n}명 · 총 {$it->qty}권이 나누어떨어지지 않음 — 수량 확인 필요";
                        if ($per < 1) {
                            $per  = (int) $it->qty;
                            $note = "학생 {$n}명 · 총 {$it->qty}권 — 배분 확인 필요";
                        }
                        if (! $st->parent_address) $note = trim($note . ' / 주소 없음 — 확인 필요');

                        $addRow($to, array_merge($head, [
                            $it->title_snapshot, (string) $it->isbn_snapshot, $per,
                        ], $tail, [$note]));
                    }
                }
            }
        }

        $spreadsheet = new Spreadsheet();

        // ── 시트 1: 출고목록 — 배송지로 묶고, 한 덩어리마다 배송번호를 매긴다
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('출고목록');
        $sheet->fromArray(['배송번호', '주문번호', '주문일', '확정일', '학원',
                           '받는분', '연락처', '주소', '상세주소',
                           '교재명', 'ISBN', '수량', '배송방식', '배송메모', '비고'], null, 'A1');

        $r = 2; $no = 0;
        foreach ($deliveries as $d) {
            $no++;
            foreach ($d['rows'] as $i => $row) {
                // 배송번호·받는분·주소는 덩어리 첫 줄에만 — 물류가 송장 단위를 눈으로 세게 한다
                $first = $i === 0;
                $this->writeRow($sheet, $r++, array_merge(
                    [$first ? $no : ''],
                    array_slice($row, 0, 4),                        // 주문번호·주문일·확정일·학원
                    $first ? $d['to'] : ['', '', '', ''],           // 받는분·연락처·주소·상세
                    array_slice($row, 4)                            // 교재명·ISBN·수량·배송방식·메모·비고
                ));
            }
        }
        $this->styleSheet($sheet, $r - 1);

        // ── 시트 2: 배송지목록 — 송장 몇 장인지, 배송지마다 몇 권인지
        $dest = $spreadsheet->createSheet();
        $dest->setTitle('배송지목록');
        $dest->fromArray(['배송번호', '받는분', '연락처', '주소', '상세주소',
                          '교재 종수', '총수량', '주문번호', '비고'], null, 'A1');
        $dr = 2; $no = 0;
        foreach ($deliveries as $d) {
            $no++;
            $qty   = array_sum(array_map(fn ($x) => (int) $x[6], $d['rows']));
            $notes = array_values(array_unique(array_filter(array_map(fn ($x) => (string) $x[9], $d['rows']))));
            $ordNos = implode(', ', array_values(array_unique(array_map(fn ($x) => (string) $x[0], $d['rows']))));
            $dest->fromArray(array_merge([$no], $d['to'], [
                count($d['rows']), $qty, $ordNos, implode(' / ', $notes),
            ]), null, 'A' . $dr);
            $dest->getCell('C' . $dr)->setValueExplicit((string) $d['to'][1], DataType::TYPE_STRING);
            $dest->getCell('H' . $dr)->setValueExplicit($ordNos, DataType::TYPE_STRING);
            $dr++;
        }
        $this->styleSheet($dest, $dr - 1);

        // ── 시트 3: 주문요약
        $sum = $spreadsheet->createSheet();
        $sum->setTitle('주문요약');
        $sum->fromArray(['주문번호', '주문일', '확정일', '학원', '학급', '거래구분', '수령형태',
                         '영업자', '품목수', '총수량', '주문금액', '상태'], null, 'A1');
        $sr = 2;
        $statusNames = ['confirmed' => '확정', 'accepted' => '총판 접수', 'shipped' => '출고',
                        'in_transit' => '배송중', 'completed' => '완료'];
        foreach ($orders as $o) {
            $oItems  = $items[$o->id] ?? collect();
            $isWhole = ($o->trade_type ?? 'retail') === 'wholesale';
            $sum->fromArray([
                $o->order_no,
                substr((string) ($o->requested_at ?? $o->created_at), 0, 10),
                $o->confirmed_at ? substr((string) $o->confirmed_at, 0, 10) : '',
                $o->vendor_name, $o->class_name ?? '',
                $isWhole ? '도매' : '소매',
                ($isWhole || ($o->ship_to_type ?? 'parent') === 'vendor') ? '학원 일괄' : '학부모 개별',
                $o->agent_name ?? '',
                $oItems->count(), (int) $oItems->sum('qty'), (int) $o->total_amount,
                $statusNames[$o->status_code] ?? $o->status_code,
            ], null, 'A' . $sr);
            $sum->getCell('A' . $sr)->setValueExplicit((string) $o->order_no, DataType::TYPE_STRING);
            $sr++;
        }
        $this->styleSheet($sum, $sr - 1);

        $spreadsheet->setActiveSheetIndex(0);

        AuditLog::log('orders', 0, 'logistics_export', null, [
            'count' => $orders->count(), 'order_nos' => $orders->pluck('order_no')->take(50)->all(),
            'skipped' => $skipped, 'not_confirmed' => $exclude->count(),
        ]);

        $writer   = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $filename = '출고요청_' . now()->format('Ymd_His') . '_' . $orders->count() . '건.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'        => 'no-cache',
        ]);
    }

    /** 한 줄 쓰기 — 주문번호·연락처·ISBN 은 엑셀이 숫자로 바꾸지 않게 텍스트 고정 */
    private function writeRow($sheet, int $row, array $values): void
    {
        $sheet->fromArray($values, null, 'A' . $row);
        foreach (['B', 'G', 'K'] as $col) {   // 주문번호, 연락처, ISBN
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
