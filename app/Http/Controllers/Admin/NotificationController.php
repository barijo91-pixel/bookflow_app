<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\AligoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class NotificationController extends Controller
{
    // -------------------- 수동 문자(SMS) 발송 --------------------
    public function compose()
    {
        // 문자 받을 수 있는 사용자 (휴대폰 등록된 활성 계정)
        $users = DB::table('users')
            ->whereIn('role_code', ['distributor', 'agent', 'academy'])
            ->where('status_code', 'active')
            ->whereNotNull('phone')->where('phone', '!=', '')
            ->orderBy('role_code')->orderBy('name')
            ->get(['id', 'name', 'login_id', 'phone', 'role_code']);

        $aligoReady = app(AligoService::class)->configured();

        return view('admin.notifications.compose', compact('users', 'aligoReady'));
    }

    public function sendManual(Request $request, AligoService $aligo)
    {
        $data = $request->validate([
            'message'      => ['required', 'string', 'max:2000'],
            'user_ids'     => ['nullable', 'array'],
            'user_ids.*'   => ['integer'],
            'extra_phones' => ['nullable', 'string', 'max:2000'],
        ], [
            'message.required' => '보낼 내용을 입력하세요.',
        ]);

        // 대상 번호 수집 (선택 사용자 + 직접 입력, 중복 제거)
        $phones = [];
        if (! empty($data['user_ids'])) {
            foreach (DB::table('users')->whereIn('id', $data['user_ids'])->pluck('phone') as $p) {
                $p = preg_replace('/[^0-9]/', '', (string) $p);
                if ($p) $phones[$p] = true;
            }
        }
        if (! empty($data['extra_phones'])) {
            foreach (preg_split('/[\s,]+/', $data['extra_phones']) as $p) {
                $p = preg_replace('/[^0-9]/', '', (string) $p);
                if ($p) $phones[$p] = true;
            }
        }
        $phones = array_keys($phones);

        if (empty($phones)) {
            return back()->withInput()->with('error', '발송 대상이 없습니다. 사용자를 선택하거나 번호를 입력하세요.');
        }
        if (! $aligo->configured()) {
            return back()->withInput()->with('error', '알리고 키가 설정되지 않았습니다. (사이트 설정 > 외부 연동에서 입력 후 발신번호 등록·충전 필요)');
        }

        $sent = 0; $failed = 0; $skipped = 0;
        foreach ($phones as $phone) {
            $res = $aligo->sendSms($phone, $data['message'], 'BookSys');
            $ok = ! empty($res['ok']);
            if ($ok) $sent++;
            elseif (! empty($res['skipped'])) $skipped++;
            else $failed++;

            DB::table('notifications')->insert([
                'event_code'          => 'admin.manual',
                'channel'             => 'sms',
                'recipient_type'      => 'raw',
                'recipient_id'        => null,
                'recipient_phone'     => $phone,
                'recipient_email'     => null,
                'subject'             => null,
                'payload'             => $data['message'],
                'context'             => null,
                'status'              => $ok ? 'sent' : (! empty($res['skipped']) ? 'skipped' : 'failed'),
                'provider'            => $res['provider'] ?? 'aligo-sms',
                'provider_message_id' => $res['message_id'] ?? null,
                'response_body'       => isset($res['response']) ? json_encode($res['response'], JSON_UNESCAPED_UNICODE) : ($res['error'] ?? null),
                'attempts'            => 1,
                'sent_at'             => $ok ? now() : null,
                'failed_at'           => (! $ok && empty($res['skipped'])) ? now() : null,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);
        }

        AuditLog::log('notifications', null, 'admin_manual_sms', null, [
            'count' => count($phones), 'sent' => $sent, 'failed' => $failed,
        ]);

        $msg = "문자 발송 — 성공 {$sent}건".
            ($failed ? ", 실패 {$failed}건" : '').
            ($skipped ? ", 건너뜀 {$skipped}건" : '').
            " (대상 ".count($phones)."명)";

        return redirect()->route('admin.notifications.logs')->with('success', $msg);
    }

    // -------------------- TEMPLATES --------------------
    public function templates()
    {
        $templates = DB::table('notification_templates')
            ->orderBy('event_code')
            ->orderBy('channel')
            ->get();

        $events = DB::table('codes')->where('group_code', 'notify_event')->orderBy('sort_order')->get();
        $channels = DB::table('codes')->where('group_code', 'notify_channel')->orderBy('sort_order')->get();

        return view('admin.notifications.templates', compact('templates', 'events', 'channels'));
    }

    public function updateTemplate(Request $request, int $id)
    {
        $data = $request->validate([
            'name'                => ['required', 'string', 'max:100'],
            'aligo_template_code' => ['nullable', 'string', 'max:50'],
            'subject'             => ['nullable', 'string', 'max:200'],
            'body'                => ['required', 'string'],
            'is_active'           => ['nullable', 'boolean'],
        ]);
        DB::table('notification_templates')->where('id', $id)->update([
            'name'                => $data['name'],
            'aligo_template_code' => $data['aligo_template_code'] ?? null,
            'subject'             => $data['subject'] ?? null,
            'body'                => $data['body'],
            'is_active'           => $request->boolean('is_active'),
            'updated_at'          => now(),
        ]);
        return back()->with('success', '템플릿이 저장되었습니다.');
    }

    // -------------------- LOGS --------------------
    public function logs(Request $request)
    {
        $event   = $request->query('event');
        $channel = $request->query('channel');
        $status  = $request->query('status');
        $q       = trim((string) $request->query('q'));

        $query = DB::table('notifications')->orderByDesc('id');
        if ($event)   $query->where('event_code', $event);
        if ($channel) $query->where('channel', $channel);
        if ($status)  $query->where('status', $status);
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('recipient_phone', 'like', "%{$q}%")
                  ->orWhere('recipient_email', 'like', "%{$q}%")
                  ->orWhere('payload', 'like', "%{$q}%");
            });
        }
        $logs = $query->paginate(50)->withQueryString();

        $events = DB::table('codes')->where('group_code', 'notify_event')->orderBy('sort_order')->get();
        $channels = DB::table('codes')->where('group_code', 'notify_channel')->orderBy('sort_order')->get();

        $summary = [
            'total'   => DB::table('notifications')->count(),
            'sent'    => DB::table('notifications')->where('status', 'sent')->count(),
            'failed'  => DB::table('notifications')->where('status', 'failed')->count(),
            'skipped' => DB::table('notifications')->where('status', 'skipped')->count(),
        ];

        return view('admin.notifications.logs', compact('logs', 'events', 'channels', 'event', 'channel', 'status', 'q', 'summary'));
    }
}
