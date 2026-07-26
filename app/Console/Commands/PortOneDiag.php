<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Services\PortOneService;

/**
 * PortOne 결제 설정/네트워크/인증 진단.
 * 시크릿 실제 값은 출력하지 않고 형태(접두어/길이)만 보여준다.
 *
 * 사용:
 *   php artisan booksys:portone-diag
 *   php artisan booksys:portone-diag --payment-id=store-15-1737...   # 실제 결제ID 상태 조회
 */
class PortOneDiag extends Command
{
    protected $signature = 'booksys:portone-diag {--payment-id= : 특정 결제ID 상태 조회}';
    protected $description = 'PortOne 설정/네트워크/인증 진단 (시크릿 값 노출 없음)';

    public function handle(): int
    {
        $storeId = (string) setting('portone_v2_store_id', '');
        $card    = (string) setting('portone_channel_card', '');
        $kakao   = (string) setting('portone_channel_kakao', '');
        $secret  = (string) setting('portone_v2_api_secret', '');

        $this->info('=== PortOne 설정 ===');
        $this->line('portone_active : ' . var_export(setting('portone_active'), true));
        $this->line('isActive()     : ' . (PortOneService::isActive() ? 'true' : 'false'));
        $this->newLine();

        $this->line('Store ID     : len ' . strlen($storeId) . '  ' . $this->prefixNote($storeId, 'store-'));
        $this->line('카드 채널    : len ' . strlen($card) . '  ' . ($card === '' ? '(비어있음 → 카드결제 숨김)' : $this->prefixNote($card, 'channel-key-')));
        $this->line('카카오 채널  : len ' . strlen($kakao) . '  ' . ($kakao === '' ? '(비어있음 → 카카오페이 숨김)' : $this->prefixNote($kakao, 'channel-key-')));

        // API Secret 은 store-/channel-key- 로 시작하면 안 됨
        $secBad = str_starts_with($secret, 'store-') || str_starts_with($secret, 'channel-key-');
        $this->line('API Secret   : len ' . strlen($secret) . '  ' . (
            $secret === '' ? '[X] 비어있음'
                : ($secBad ? '[X] store-/channel-key- 로 시작 → 잘못된 값' : '[OK] 접두어 정상')
        ));

        $methods = PortOneService::methods();
        $labels  = array_map(fn ($m) => $m['label'] . '(' . $m['payMethod'] . ')', $methods);
        $this->line('활성 결제수단: ' . (empty($methods) ? '[X] 없음' : '[OK] ' . implode(', ', $labels)));
        $this->newLine();

        // 네트워크 + 인증 테스트
        $this->info('=== 네트워크/인증 테스트 (api.portone.io) ===');
        $testId = (string) ($this->option('payment-id') ?: 'diag-nonexistent');
        $this->line('조회 결제ID : ' . $testId);
        try {
            $r = Http::timeout(8)
                ->withHeaders(['Authorization' => 'PortOne ' . $secret])
                ->get('https://api.portone.io/payments/' . rawurlencode($testId));
            $st = $r->status();
            $this->line('HTTP status : ' . $st);
            $this->line('body        : ' . substr($r->body(), 0, 300));
            $this->newLine();

            match (true) {
                $st === 401 => $this->error('→ 401: API Secret 이 틀렸습니다. (PortOne 콘솔에서 V2 API Secret 재발급)'),
                $st === 404 => $this->info('→ 404: 네트워크·인증 정상. 해당 결제ID만 없음(=설정은 OK).'),
                $st === 200 => $this->info('→ 200: 결제 조회 성공 — 상태는 위 body 의 "status" 확인. 설정 완전 정상.'),
                default     => $this->warn('→ ' . $st . ': 예상 밖 응답. 위 body 확인.'),
            };
        } catch (\Throwable $e) {
            $this->error('NETERR: ' . get_class($e) . ': ' . $e->getMessage());
            $this->warn('→ 서버에서 api.portone.io 로 나가는 인터넷이 막혀 있을 수 있습니다 (egress 차단).');
        }

        return self::SUCCESS;
    }

    private function prefixNote(string $v, string $pfx): string
    {
        if ($v === '') return '[X] 비어있음';
        return str_starts_with($v, $pfx) ? '[OK]' : '[!] 접두어 ' . $pfx . ' 아님';
    }
}
