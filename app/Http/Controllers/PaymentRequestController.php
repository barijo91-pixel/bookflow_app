<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\NotificationService;
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
                'b.title as book_title')
            ->get();

        // 이미 보낸 요청
        $existing = DB::table('payment_requests')->where('order_id', $orderId)
            ->orderByDesc('id')->get();

        return view('public.mypage.payment_request_create', compact(
            'order', 'vendor', 'classes', 'items', 'existing'
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
        $distributor = DB::table('users')->where('role_code', 'distributor')
            ->select('name', 'bank_code', 'bank_account', 'bank_holder', 'phone')
            ->first(); // 운영 v1: 첫 distributor의 계좌 사용

        $bankName = null;
        if ($distributor && $distributor->bank_code) {
            $bankName = DB::table('codes')->where('group_code', 'bank')
                ->where('code', $distributor->bank_code)->value('name');
        }

        $items = json_decode($pr->items_snapshot ?? '[]', true) ?: [];

        return view('public.pay.show', compact('pr', 'vendor', 'distributor', 'bankName', 'items'));
    }
}
