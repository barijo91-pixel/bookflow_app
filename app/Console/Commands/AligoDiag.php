<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AligoService;

/**
 * 알리고 문자 발송 진단 — 실제 테스트 문자를 보내고 알리고 응답 원문을 그대로 출력.
 * (에러코드/메시지를 눈으로 확인해 IP/발신번호/잔액/키 문제를 바로 판별)
 *
 * 사용:
 *   php artisan booksys:aligo-diag 01012345678
 *   php artisan booksys:aligo-diag 01012345678 --message="테스트"
 */
class AligoDiag extends Command
{
    protected $signature = 'booksys:aligo-diag {phone : 테스트 문자 받을 번호} {--message= : 보낼 내용}';
    protected $description = '알리고 문자 발송 진단 (실제 테스트 발송 + 응답 원문 출력)';

    public function handle(AligoService $aligo): int
    {
        $apiKey = (string) (setting('aligo_api_key') ?: env('ALIGO_API_KEY'));
        $userId = (string) (setting('aligo_user_id') ?: env('ALIGO_USER_ID'));
        $sender = (string) (setting('aligo_sender') ?: env('ALIGO_SENDER'));

        $this->info('=== 알리고 설정 ===');
        $this->line('API Key    : ' . ($apiKey === '' ? '[X] 비어있음' : '[OK] len ' . strlen($apiKey)));
        $this->line('User ID    : ' . ($userId === '' ? '[X] 비어있음' : $userId));
        $this->line('발신번호   : ' . ($sender === '' ? '[X] 비어있음' : $sender));
        $this->line('configured(): ' . ($aligo->configured() ? 'true' : 'false'));
        $this->newLine();

        $phone = preg_replace('/[^0-9]/', '', (string) $this->argument('phone'));
        $msg   = (string) ($this->option('message') ?: '[BookSys] 알리고 문자 발송 테스트입니다.');

        $this->info('=== 테스트 문자 발송 → ' . $phone . ' ===');
        $res = $aligo->sendSms($phone, $msg, 'BookSys');

        $this->line('결과(ok)   : ' . (($res['ok'] ?? false) ? 'true (성공)' : 'false (실패)'));
        $this->line('에러       : ' . ($res['error'] ?? '(없음)'));
        $this->newLine();
        $this->line('알리고 응답 원문:');
        $this->line(json_encode($res['response'] ?? $res, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $this->newLine();

        $rc = (string) ($res['response']['result_code'] ?? '');
        $rmsg = (string) ($res['response']['message'] ?? ($res['error'] ?? ''));
        match (true) {
            $rc === '1' => $this->info('→ 성공! 문자 도착했는지 확인하세요.'),
            $rc === ''  => $this->warn('→ 응답 없음/네트워크/키 미설정. 위 에러 확인.'),
            default     => $this->error('→ 실패 코드 ' . $rc . ' : ' . $rmsg),
        };

        $this->newLine();
        $this->line('참고 — 자주 나오는 알리고 에러:');
        $this->line('  -101  인증되지 않은 발송 IP  → 서버 egress IP를 알리고에 등록');
        $this->line('  -102  인증 실패             → API Key / User ID 불일치');
        $this->line('  발신번호 관련              → 발신번호 미등록/미승인');
        $this->line('  잔액/충전 관련             → 알리고 충전 필요');

        return self::SUCCESS;
    }
}
