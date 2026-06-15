<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\PaymentRequest;
use App\Services\NotificationService;
use App\Services\PortOneService;
use App\Services\SettlementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentRequestController extends Controller
{
    /**
     * 학원이 주문에서 학부모에게 결제요청 보내기 — 폼 진입
     * /mypage/orders/{id}/payment-requests/create
     */
    public function create(int $orderId)
    {
        $user = Auth::user();
        if ($user->role_code !== 'academy') abort(403);

        $order = DB::table('orders')->where('id', $orderId)->whereNull('deleted_at')->first();
        abort_if(! $order, 404);

        // 본인 학원의 주문인지
        $vendorIds = DB::table('vendor_users')->where('user_id', $user->id)->pluck('vendor_id')->toArray();
        if (! in_array($order->vendor_id, $vendorIds)) abort(403);

        $vendor = DB::table('vendors')->find($order->vendor_id);

        // 학급 목록 (active)
        $classes = DB::table('academy_classes')
            ->where('vendor_id', $order->vendor_id)
            ->where('status', 'active')
            ->orderBy('name')->get(['id', 'name', 'grade_code']);

        // 주문 도서
        $items = DB::table('order_items as oi')
            ->leftJoin('books as b', 'b.id', '=', 'oi.book_id')
            ->where('oi.order_id', $orderId)
            ->select('oi.id', 'oi.book_id', 'oi.title_snapshot', 'oi.qty', 'oi.unit_price', 'oi.line_total',
                'oi.list_price', 'b.title as book_title')
            ->get();

        // 학생 1명당 권장 결제금액 = 도서 1세트 정가 합계 × 소매율(90%, 도서정가제)
        // 정가가 없으면 단가로 대체
        $setListPrice = (int) $items->sum(fn ($it) => (int) ($it->list_price ?: $it->unit_price));
        $recommendedAmount = (int) round($setListPrice * \App\Services\SettlementService::RATE_B2C_RETAIL);

        // 이미 보낸 요청
        $existing = DB::table('payment_requests')->where('order_id', $orderId)
            ->orderByDesc('id')->get();

        return view('public.mypage.payment_request_create', compact(
            'order', 'vendor', 'classes', 'items', 'existing', 'recommendedAmount', 'setListPrice'
        ));
    }

    /**
     * 선택된 학급의 학생/학부모 목록 (Ajax)
     * /mypage/classes/{classId}/students-with-parents
     */
    public function studentsWithParents(int $classId)
    {
        $user = Auth::user();
        if ($user->role_code !== 'academy') abort(403);

        $class = DB::table('academy_classes')->where('id', $classId)->first();
        abort_if(! $class, 404);

        // 본인 학원의 학급
        $vendorIds = DB::table('vendor_users')->where('user_id', $user->id)->pluck('vendor_id')->toArray();
        if (! in_array($class->vendor_id, $vendorIds)) abort(403);

        $rows = DB::table('students as s')
            ->leftJoin('parents as p', 'p.id', '=', 's.parent_id')
            ->where('s.class_id', $classId)
            ->whereNull('s.deleted_at')
            ->select(
                's.id as student_id', 's.name as student_name', 's.grade_code',
                'p.id as parent_id', 'p.name as parent_name', 'p.phone as parent_phone',
                'p.email as parent_email'
            )
            ->orderBy('s.name')->get();

        return response()->json($rows);
    }

    /**
     * 결제요청 일괄 생성 + 알림 발송
     * POST /mypage/orders/{id}/payment-requests
     */
    public function store(Request $request, int $orderId, NotificationService $notify)
    {
        $user = Auth::user();
        if ($user->role_code !== 'academy') abort(403);

        $order = DB::table('orders')->where('id', $orderId)->whereNull('deleted_at')->first();
        abort_if(! $order, 404);

        $vendorIds = DB::table('vendor_users')->where('user_id', $user->id)->pluck('vendor_id')->toArray();
        if (! in_array($order->vendor_id, $vendorIds)) abort(403);

        $data = $request->validate([
            'class_id'                  => ['nullable', 'integer'],
            'recipients'                => ['required', 'array', 'min:1'],
            'recipients.*.student_id'   => ['required', 'integer'],
            'recipients.*.amount'       => ['required', 'integer', 'min:1', 'max:99999999'],
            'memo'                      => ['nullable', 'string', 'max:500'],
        ]);

        $vendor = DB::table('vendors')->find($order->vendor_id);

        // 학생 정보 일괄 조회
        $studentIds = array_column($data['recipients'], 'student_id');
        $students = DB::table('students as s')
            ->leftJoin('parents as p', 'p.id', '=', 's.parent_id')
            ->whereIn('s.id', $studentIds)
            ->select('s.id', 's.name', 's.parent_id',
                'p.name as parent_name', 'p.phone as parent_phone')
            ->get()->keyBy('id');

        $orderItems = DB::table('order_items')->where('order_id', $orderId)
            ->select('book_id', 'title_snapshot', 'qty', 'unit_price', 'line_total')->get();
        $itemsSnap = $orderItems->map(fn ($r) => [
            'title' => $r->title_snapshot, 'qty' => (int) $r->qty, 'price' => (int) $r->unit_price,
        ])->all();

        $created = 0; $failed = 0; $sent = 0;
        $expiresAt = now()->addDays(14);

        foreach ($data['recipients'] as $r) {
            $student = $students->get($r['student_id']);
            if (! $student || ! $student->parent_phone) { $failed++; continue; }

            DB::transaction(function () use (
                &$created, $r, $student, $vendor, $orderId, $user, $data, $itemsSnap, $expiresAt
            ) {
                $token = Str::random(40);
                DB::table('payment_requests')->insert([
                    'token'          => $token,
                    'order_id'       => $orderId,
                    'vendor_id'      => $vendor->id,
                    'class_id'       => $data['class_id'] ?? null,
                    'student_id'     => $student->id,
                    'parent_id'      => $student->parent_id,
                    'parent_name'    => $student->parent_name,
                    'parent_phone'   => $student->parent_phone,
                    'student_name'   => $student->name,
                    'amount'         => (int) $r['amount'],
                    'items_snapshot' => json_encode($itemsSnap, JSON_UNESCAPED_UNICODE),
                    'status'         => 'sent',
                    'created_by'     => $user->id,
                    'sent_at'        => now(),
                    'expires_at'     => $expiresAt,
                    'memo'           => $data['memo'] ?? null,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
                $created++;
            });

            // 알림 발송 — 토큰 URL 포함
            $token = DB::table('payment_requests')->where('order_id', $orderId)
                ->where('student_id', $student->id)->orderByDesc('id')->value('token');
            $payUrl = url('/pay/'.$token);

            try {
                $notify->send('payment.requested', [
                    'vendor_name'  => $vendor->name,
                    'student_name' => $student->name,
                    'parent_name'  => $student->parent_name ?? '학부모님',
                    'amount'       => (int) $r['amount'],
                    'amount_fmt'   => number_format((int) $r['amount']),
                    'pay_url'      => $payUrl,
                ], [
                    ['type' => 'parent', 'id' => $student->parent_id, 'phone' => $student->parent_phone, 'name' => $student->parent_name],
                ]);
                $sent++;
            } catch (\Throwable $e) {
                // 알림 실패는 결제요청 자체에 영향 X
            }
        }

        AuditLog::log('payment_requests', $orderId, 'bulk_create', null, [
            'order_id' => $orderId, 'created' => $created, 'sent' => $sent, 'failed' => $failed,
        ]);

        return back()->with('success', "결제요청 {$created}건 생성 · 알림 {$sent}건 발송"
            .($failed > 0 ? " · 실패 {$failed}건(학부모 연락처 없음)" : ''));
    }

    /**
     * 학부모 결제 페이지 (공개 — 토큰만 있으면 접근)
     * GET /pay/{token}
     */
    public function publicShow(string $token)
    {
        $pr = DB::table('payment_requests')->where('token', $token)->first();
        abort_if(! $pr, 404, '결제 요청을 찾을 수 없습니다.');

        // 만료 체크
        if ($pr->expires_at && now()->gt($pr->expires_at)) {
            DB::table('payment_requests')->where('id', $pr->id)->update([
                'status' => 'expired', 'updated_at' => now()
            ]);
            $pr->status = 'expired';
        }

        // 처음 열어본 시각 기록
        if (! $pr->viewed_at) {
            DB::table('payment_requests')->where('id', $pr->id)->update([
                'viewed_at' => now(),
                'status' => $pr->status === 'sent' ? 'viewed' : $pr->status,
                'updated_at' => now(),
            ]);
        }

        $vendor = DB::table('vendors')->find($pr->vendor_id);

        // 총판 결정: 주문에 라우팅된 총판 우선 (다중 총판 지원)
        // fallback: 영업자의 첫 총판 → 첫 distributor
        $order = DB::table('orders')->find($pr->order_id);
        $distributorId = $order?->distributor_user_id;
        if (! $distributorId && $order) {
            $distributorId = DB::table('user_relations')
                ->where('child_user_id', $order->agent_user_id)
                ->where('relation_type', 'distributor_agent')
                ->where('status', 'active')
                ->orderBy('id')->value('parent_user_id');
        }
        $distributor = $distributorId
            ? DB::table('users')->where('id', $distributorId)
                ->select('name', 'bank_code', 'bank_account', 'bank_holder', 'phone')->first()
            : DB::table('users')->where('role_code', 'distributor')
                ->select('name', 'bank_code', 'bank_account', 'bank_holder', 'phone')
                ->orderBy('id')->first();

        $bankName = null;
        if ($distributor && $distributor->bank_code) {
            $bankName = DB::table('codes')->where('group_code', 'bank')
                ->where('code', $distributor->bank_code)->value('name');
        }

        $items = json_decode($pr->items_snapshot ?? '[]', true) ?: [];
        $portOneActive = PortOneService::isActive();
        $portOneImpUid = PortOneService::impUid();

        return view('public.pay.show', compact('pr', 'vendor', 'distributor', 'bankName', 'items', 'portOneActive', 'portOneImpUid'));
    }

    /**
     * Mock PG 결제 처리 — 실 PG 연동(C-3) 전 테스트용
     * POST /pay/{token}/mock-pay
     *
     * 처리:
     *  1. payment_request.status = 'paid'
     *  2. SettlementService::createFromPaymentRequest()로 정산 레코드 자동 생성
     *  3. 결제 완료 화면으로 리다이렉트
     */
    public function mockPay(Request $request, string $token)
    {
        // 보안: 실 PG(PortOne) 활성 시 mock 결제 차단 — 결제 우회 방지
        if (PortOneService::isActive()) {
            abort(403, '실 결제 모드에서는 사용할 수 없습니다.');
        }

        $pr = PaymentRequest::where('token', $token)->first();
        abort_if(! $pr, 404, '결제 요청을 찾을 수 없습니다.');

        if ($pr->status === 'paid') {
            return redirect()->route('public.pay', $token)->with('info', '이미 결제 완료된 요청입니다.');
        }

        if (in_array($pr->status, ['expired', 'canceled'])) {
            return redirect()->route('public.pay', $token)
                ->with('error', '결제가 불가능한 상태입니다. (' . $pr->status . ')');
        }

        DB::transaction(function () use ($pr) {
            // 결제 상태 업데이트
            $pr->update([
                'status'  => 'paid',
                'paid_at' => now(),
            ]);

            // 정산 레코드 자동 생성
            $mockTxId = 'MOCK-' . strtoupper(Str::random(10));
            $settlement = SettlementService::createFromPaymentRequest($pr, $mockTxId);

            AuditLog::log('payment_requests', $pr->id, 'mock_paid', null, [
                'pg_transaction_id' => $mockTxId,
                'settlement_id'     => $settlement->id,
                'parent_paid'       => $pr->amount,
                'agent_payout'      => $settlement->agent_payout,
                'dist_net'          => $settlement->dist_net,
            ]);
        });

        return redirect()->route('public.pay', $token)
            ->with('success', '결제가 완료되었습니다. (테스트 모드)');
    }

    /**
     * PortOne 결제 완료 콜백 (실 PG 결제)
     * POST /pay/{token}/portone-complete
     *
     * 요청: imp_uid (PortOne 결제 식별), merchant_uid (가맹점 주문번호)
     *
     * 처리:
     *  1. PortOne API로 실제 결제 정보 검증 (위변조 방지)
     *  2. 결제 금액 == 청구 금액 확인
     *  3. payment_request.status='paid'
     *  4. SettlementService 호출 → SettlementRecord 생성
     */
    public function portOneComplete(Request $request, string $token)
    {
        $pr = PaymentRequest::where('token', $token)->first();
        abort_if(! $pr, 404, '결제 요청을 찾을 수 없습니다.');

        $data = $request->validate([
            'imp_uid'      => ['required', 'string', 'max:100'],
            'merchant_uid' => ['required', 'string', 'max:100'],
        ]);

        if ($pr->status === 'paid') {
            return response()->json(['success' => true, 'message' => '이미 결제 완료']);
        }

        // PortOne API로 결제 검증
        $payment = PortOneService::getPayment($data['imp_uid']);
        if (! $payment) {
            return response()->json([
                'success' => false,
                'message' => 'PG 결제 정보를 확인할 수 없습니다.',
            ], 400);
        }

        // 위변조 방지: 결제 상태 + 금액 검증
        if (($payment['status'] ?? '') !== 'paid') {
            return response()->json([
                'success' => false,
                'message' => '결제 상태 이상: ' . ($payment['status'] ?? '?'),
            ], 400);
        }

        if ((int) ($payment['amount'] ?? 0) !== (int) $pr->amount) {
            // 결제 금액 위변조 → 자동 환불
            PortOneService::cancel($data['imp_uid'], (int) ($payment['amount'] ?? 0), '결제 금액 불일치 자동 환불');
            AuditLog::log('payment_requests', $pr->id, 'amount_mismatch', null, [
                'expected' => $pr->amount,
                'actual'   => $payment['amount'] ?? 0,
                'imp_uid'  => $data['imp_uid'],
            ]);
            return response()->json([
                'success' => false,
                'message' => '결제 금액 불일치. 자동 환불 처리되었습니다.',
            ], 400);
        }

        DB::transaction(function () use ($pr, $data, $payment) {
            $pr->update([
                'status'  => 'paid',
                'paid_at' => now(),
            ]);

            $settlement = SettlementService::createFromPaymentRequest($pr, $data['imp_uid']);

            AuditLog::log('payment_requests', $pr->id, 'portone_paid', null, [
                'imp_uid'          => $data['imp_uid'],
                'merchant_uid'     => $data['merchant_uid'],
                'pg_provider'      => $payment['pg_provider'] ?? '',
                'pay_method'       => $payment['pay_method'] ?? '',
                'settlement_id'    => $settlement->id,
                'parent_paid'      => $pr->amount,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => '결제가 완료되었습니다.',
            'redirect_url' => route('public.pay', $token),
        ]);
    }
}
