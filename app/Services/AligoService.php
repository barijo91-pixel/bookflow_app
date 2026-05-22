<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 알리고(Aligo) — 카카오 알림톡 + SMS 발송 래퍼
 *
 * 키 미설정 시: 호출 자체를 건너뛰고 [ok=false, skipped=true]를 반환.
 * 호출하는 쪽(NotificationService)이 이걸 보고 DB에는 'skipped' 상태로 기록한다.
 */
class AligoService
{
    private const ALIMTALK_URL = 'https://kakaoapi.aligo.in/akv10/alimtalk/send/';
    private const SMS_URL      = 'https://apis.aligo.in/send/';

    public function configured(): bool
    {
        return $this->apiKey() && $this->userId() && $this->sender();
    }

    private function apiKey(): ?string    { return setting('aligo_api_key') ?: env('ALIGO_API_KEY'); }
    private function userId(): ?string    { return setting('aligo_user_id') ?: env('ALIGO_USER_ID'); }
    private function senderKey(): ?string { return setting('aligo_sender_key') ?: env('ALIGO_SENDER_KEY'); }
    private function sender(): ?string    { return setting('aligo_sender') ?: env('ALIGO_SENDER'); }

    /**
     * 카카오 알림톡 발송 (실패 시 SMS 폴백)
     *
     * @return array{ok:bool, skipped?:bool, provider:string, response?:array, message_id?:string, error?:string}
     */
    public function sendAlimtalk(string $phone, string $templateCode, string $message, ?string $subject = null): array
    {
        if (! $this->configured() || ! $this->senderKey() || ! $templateCode) {
            return ['ok' => false, 'skipped' => true, 'provider' => 'aligo', 'error' => '알리고 키 미설정 또는 템플릿 코드 없음'];
        }

        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (! $phone) return ['ok' => false, 'provider' => 'aligo', 'error' => '잘못된 전화번호'];

        try {
            $response = Http::asMultipart()->timeout(15)->post(self::ALIMTALK_URL, [
                ['name' => 'apikey',      'contents' => $this->apiKey()],
                ['name' => 'userid',      'contents' => $this->userId()],
                ['name' => 'senderkey',   'contents' => $this->senderKey()],
                ['name' => 'tpl_code',    'contents' => $templateCode],
                ['name' => 'sender',      'contents' => $this->sender()],
                ['name' => 'receiver_1',  'contents' => $phone],
                ['name' => 'subject_1',   'contents' => $subject ?: 'BookFlow'],
                ['name' => 'msg_1',       'contents' => $message],
                ['name' => 'failover_1',  'contents' => 'Y'],     // 실패 시 SMS 자동 폴백
                ['name' => 'fmsg_1',      'contents' => $message], // 폴백 메시지
                ['name' => 'fsubject_1',  'contents' => $subject ?: 'BookFlow'],
                ['name' => 'testMode',    'contents' => app()->environment('production') ? 'N' : 'N'],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Aligo Alimtalk failed', ['phone' => $phone, 'error' => $e->getMessage()]);
            return ['ok' => false, 'provider' => 'aligo', 'error' => $e->getMessage()];
        }

        $body = $response->json() ?? [];
        $code = (string) ($body['code'] ?? '');
        $ok = $code === '0';
        return [
            'ok'         => $ok,
            'provider'   => 'aligo',
            'response'   => $body,
            'message_id' => (string) ($body['msg_id'] ?? ''),
            'error'      => $ok ? null : ($body['message'] ?? '발송 실패'),
        ];
    }

    /**
     * SMS / LMS 단문 발송 (90자 이하 = SMS, 초과 = LMS)
     */
    public function sendSms(string $phone, string $message, ?string $title = null): array
    {
        if (! $this->configured()) {
            return ['ok' => false, 'skipped' => true, 'provider' => 'aligo-sms', 'error' => '알리고 키 미설정'];
        }

        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (! $phone) return ['ok' => false, 'provider' => 'aligo-sms', 'error' => '잘못된 전화번호'];

        $msgType = mb_strlen($message) > 90 ? 'LMS' : 'SMS';

        try {
            $response = Http::asForm()->timeout(15)->post(self::SMS_URL, [
                'key'      => $this->apiKey(),
                'user_id'  => $this->userId(),
                'sender'   => $this->sender(),
                'receiver' => $phone,
                'msg'      => $message,
                'msg_type' => $msgType,
                'title'    => $title ?: 'BookFlow',
                'testmode_yn' => app()->environment('production') ? 'N' : 'N',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Aligo SMS failed', ['phone' => $phone, 'error' => $e->getMessage()]);
            return ['ok' => false, 'provider' => 'aligo-sms', 'error' => $e->getMessage()];
        }

        $body = $response->json() ?? [];
        $code = (string) ($body['result_code'] ?? '');
        $ok = $code === '1';
        return [
            'ok'         => $ok,
            'provider'   => 'aligo-sms',
            'response'   => $body,
            'message_id' => (string) ($body['msg_id'] ?? ''),
            'error'      => $ok ? null : ($body['message'] ?? '발송 실패'),
        ];
    }
}
