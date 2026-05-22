<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 알림 디스패치 도메인 서비스
 *
 * 사용 예:
 *   $notify->send('order.requested', [
 *       'order_no' => 'BF...',
 *       'vendor_name' => '이런어학원',
 *       'total_amount' => 99050,
 *   ], [
 *       ['type' => 'user', 'id' => $agentId, 'phone' => '01012345678'],
 *   ]);
 *
 * - 이벤트별 등록된 notification_templates를 채널별로 모두 발송
 * - 변수 치환: #{key} 패턴
 * - 발송 결과는 notifications 테이블에 행 단위로 기록
 * - 알리고 키 없으면 status='skipped'로 기록
 */
class NotificationService
{
    public function __construct(private AligoService $aligo) {}

    /**
     * @param  string $event       알림 이벤트 코드 (notify_event)
     * @param  array  $context     템플릿 변수
     * @param  array  $recipients  [{type, id?, phone, email?, name?}, ...]
     *                             type: user / parent / raw
     */
    public function send(string $event, array $context, array $recipients): void
    {
        if (empty($recipients)) return;

        $templates = DB::table('notification_templates')
            ->where('event_code', $event)
            ->where('is_active', true)
            ->get();

        if ($templates->isEmpty()) {
            Log::info('No notification templates for event', ['event' => $event]);
            return;
        }

        foreach ($recipients as $r) {
            $phone = $r['phone'] ?? null;
            $email = $r['email'] ?? null;

            foreach ($templates as $tpl) {
                $body = $this->render($tpl->body, $context);
                $subject = $tpl->subject ? $this->render($tpl->subject, $context) : null;

                $row = [
                    'event_code'     => $event,
                    'channel'        => $tpl->channel,
                    'recipient_type' => $r['type'] ?? 'raw',
                    'recipient_id'   => $r['id'] ?? null,
                    'recipient_phone'=> $phone,
                    'recipient_email'=> $email,
                    'subject'        => $subject,
                    'payload'        => $body,
                    'context'        => json_encode($context, JSON_UNESCAPED_UNICODE),
                    'attempts'       => 0,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ];

                // 채널별 디스패치
                $result = $this->dispatch($tpl->channel, [
                    'phone'         => $phone,
                    'email'         => $email,
                    'subject'       => $subject,
                    'body'          => $body,
                    'template_code' => $tpl->aligo_template_code,
                ]);

                $row['status']             = $result['ok'] ? 'sent' : (!empty($result['skipped']) ? 'skipped' : 'failed');
                $row['provider']           = $result['provider'] ?? null;
                $row['provider_message_id']= $result['message_id'] ?? null;
                $row['response_body']      = isset($result['response']) ? json_encode($result['response'], JSON_UNESCAPED_UNICODE) : ($result['error'] ?? null);
                $row['sent_at']            = $result['ok'] ? now() : null;
                $row['failed_at']          = (! $result['ok'] && empty($result['skipped'])) ? now() : null;
                $row['attempts']           = $result['ok'] || !empty($result['skipped']) ? 1 : 1;

                DB::table('notifications')->insert($row);
            }
        }
    }

    private function dispatch(string $channel, array $payload): array
    {
        $phone = $payload['phone'] ?? null;
        $body  = $payload['body']  ?? '';

        switch ($channel) {
            case 'alimtalk':
                if (! $phone) return ['ok' => false, 'provider' => 'aligo', 'error' => '전화번호 없음'];
                return $this->aligo->sendAlimtalk($phone, (string) ($payload['template_code'] ?? ''), $body, $payload['subject'] ?? null);

            case 'sms':
                if (! $phone) return ['ok' => false, 'provider' => 'aligo-sms', 'error' => '전화번호 없음'];
                return $this->aligo->sendSms($phone, $body, $payload['subject'] ?? null);

            case 'push':
                // FCM 미구현 → skipped
                return ['ok' => false, 'skipped' => true, 'provider' => 'fcm', 'error' => 'FCM 미구현'];

            case 'email':
                // 이메일 미구현 → skipped (MAIL_MAILER=log이므로 실제 발송 없음)
                if (empty($payload['email'])) return ['ok' => false, 'provider' => 'email', 'error' => '이메일 주소 없음'];
                try {
                    \Illuminate\Support\Facades\Mail::raw($body, function ($m) use ($payload) {
                        $m->to($payload['email'])->subject($payload['subject'] ?: 'BookFlow');
                    });
                    return ['ok' => true, 'provider' => 'mail'];
                } catch (\Throwable $e) {
                    return ['ok' => false, 'provider' => 'mail', 'error' => $e->getMessage()];
                }

            default:
                return ['ok' => false, 'provider' => $channel, 'error' => '지원하지 않는 채널'];
        }
    }

    /**
     * #{key} 패턴 치환
     */
    private function render(string $template, array $context): string
    {
        return preg_replace_callback('/#\{([a-zA-Z0-9_.]+)\}/', function ($m) use ($context) {
            $key = $m[1];
            $value = $context[$key] ?? null;
            if (is_numeric($value)) return number_format($value);
            return (string) ($value ?? '');
        }, $template);
    }

    /**
     * 휴대폰 인증번호 발송 (SMS 단독)
     */
    public function sendPhoneVerification(string $phone, string $code): void
    {
        $this->send('user.phone_verify', ['code' => $code], [
            ['type' => 'raw', 'phone' => $phone],
        ]);
    }
}
