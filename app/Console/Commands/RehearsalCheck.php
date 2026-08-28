<?php

namespace App\Console\Commands;

use App\Services\PortOneService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 오픈 전 점검 — 결제 리허설에 필요한 것들이 준비됐는지 한 번에 확인.
 * (계정·영업자 매핑·PG·알림톡·교재)
 *
 * 사용: php artisan booksys:rehearsal-check
 */
class RehearsalCheck extends Command
{
    protected $signature = 'booksys:rehearsal-check';
    protected $description = '결제 리허설 준비 상태 점검 (계정/매핑/PG/알림톡/교재)';

    public function handle(): int
    {
        $this->info('=== 결제 리허설 준비 점검 ===');
        $this->newLine();

        // 1) 학원 계정
        $this->warn('▸ 학원 계정 (로그인 가능한 학원)');
        $academies = DB::table('vendor_users as vu')
            ->join('users as u', 'u.id', '=', 'vu.user_id')
            ->join('vendors as v', 'v.id', '=', 'vu.vendor_id')
            ->whereNull('v.deleted_at')->whereNull('u.deleted_at')
            ->where('u.status_code', 'active')
            ->get(['u.login_id', 'v.name as vname', 'v.trade_type', 'v.default_ship_to_type', 'v.id as vid']);
        if ($academies->isEmpty()) {
            $this->error('  없음 — 학원 계정이 있어야 주문 가능');
        } else {
            $this->table(['아이디', '학원', '거래', '기본배송'],
                $academies->map(fn ($r) => [
                    $r->login_id, $r->vname,
                    \App\Services\TradeService::label($r->trade_type),
                    ($r->default_ship_to_type ?? 'parent') === 'vendor' ? '학원일괄' : '학부모개별',
                ])->all());
        }

        // 2) 영업자 매핑 (없으면 주문 화면이 막힘)
        $this->warn('▸ 영업자 매핑 (없으면 도서주문 불가)');
        $maps = DB::table('agent_vendor_discounts as a')
            ->join('users as u', 'u.id', '=', 'a.agent_user_id')
            ->join('vendors as v', 'v.id', '=', 'a.vendor_id')
            ->whereNull('v.deleted_at')->where('a.is_active', 1)
            ->get(['u.login_id', 'v.name as vname', 'a.discount_rate']);
        if ($maps->isEmpty()) {
            $this->error('  없음 — 학원에 담당 영업자를 연결해야 주문 가능');
        } else {
            $this->table(['영업자', '학원', '할인율'],
                $maps->map(fn ($r) => [$r->login_id, $r->vname, rtrim(rtrim($r->discount_rate, '0'), '.') . '%'])->all());
        }

        // 2-1) 영업자 ↔ 총판 소속 (영업자 1명 = 총판 1곳 정책)
        $this->warn('▸ 영업자 소속 총판 (1명 = 1곳)');
        $agents = DB::table('users as u')
            ->where('u.role_code', 'agent')->whereNull('u.deleted_at')
            ->leftJoin('user_relations as r', function ($j) {
                $j->on('r.child_user_id', '=', 'u.id')
                  ->where('r.relation_type', 'distributor_agent')
                  ->where('r.status', 'active');
            })
            ->leftJoin('users as d', 'd.id', '=', 'r.parent_user_id')
            ->groupBy('u.id', 'u.login_id', 'u.name')
            ->selectRaw('u.login_id, u.name, COUNT(r.id) as cnt, GROUP_CONCAT(d.name) as dists')
            ->get();

        $bad = 0;
        foreach ($agents as $a) {
            if ((int) $a->cnt === 1) continue;
            $bad++;
            if ((int) $a->cnt === 0) {
                $this->error("  {$a->login_id}({$a->name}) — 소속 총판 없음 → 이 영업자 학원은 주문 불가");
            } else {
                $this->error("  {$a->login_id}({$a->name}) — 총판 {$a->cnt}곳 소속({$a->dists})"
                    . " → 주문이 첫 총판으로만 감. 총판별로 아이디를 분리할 것");
            }
        }
        if ($bad === 0) {
            $this->line('  이상 없음 — 모든 영업자가 총판 1곳에 소속');
        }

        // 3) 학급/학생 (소매 결제요청에 필요)
        $classCnt   = DB::table('academy_classes')->count();
        $studentCnt = DB::table('students')->whereNull('deleted_at')->count();
        $withParent = DB::table('students as s')->join('parents as p', 'p.id', '=', 's.parent_id')
            ->whereNull('s.deleted_at')->whereNotNull('p.phone')->where('p.phone', '!=', '')->count();
        $this->warn('▸ 학급/학생 (소매 결제요청에 필요)');
        $this->line("  학급 {$classCnt}개 / 학생 {$studentCnt}명 / 학부모 연락처 있는 학생 {$withParent}명");
        if ($withParent === 0) {
            $this->error('  ※ 학부모 연락처가 있는 학생이 없으면 결제요청이 생성되지 않습니다.');
        }

        // 4) PG
        $this->warn('▸ PG (PortOne)');
        $methods = PortOneService::methods();
        $this->line('  활성: ' . (PortOneService::isActive() ? 'Y' : 'N')
            . ' / 결제수단: ' . (empty($methods) ? '(없음)' : implode(', ', array_column($methods, 'label'))));

        // 5) 알림톡/문자
        $this->warn('▸ 알림 (알리고)');
        $this->line('  발신번호: ' . (setting('aligo_sender') ?: '(없음)')
            . ' / senderkey: ' . (setting('aligo_sender_key') ? 'O' : 'X'));
        foreach (DB::table('notification_templates')
                     ->whereIn('event_code', ['order.requested', 'payment.requested'])
                     ->where('channel', 'alimtalk')->get() as $t) {
            $this->line('  ' . str_pad($t->event_code, 20) . '알림톡코드: ' . ($t->aligo_template_code ?: '(없음 → 문자로 폴백)'));
        }

        // 6) 리허설용 저가 교재
        $this->warn('▸ 리허설용 저가 교재 (금액 작은 순)');
        $books = DB::table('books')->whereNull('deleted_at')->where('status_code', 'selling')
            ->orderBy('price')->limit(5)->get(['id', 'title', 'price', 'isbn']);
        if ($books->isEmpty()) {
            $this->error('  판매중 도서가 없습니다.');
        } else {
            $this->table(['ID', '제목', '정가'],
                $books->map(fn ($b) => [$b->id, mb_strimwidth($b->title ?? '', 0, 40, '…'), number_format($b->price) . '원'])->all());
        }

        $this->newLine();
        $this->line('리허설 순서: 학원 로그인 → 도서주문 → (영업자 확정) → 결제요청 → 학부모 결제 → 정산 확인');
        return self::SUCCESS;
    }
}
