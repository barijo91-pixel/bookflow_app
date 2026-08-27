<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 반품 알림 템플릿 추가.
 *
 * 시더(NotificationTemplateSeeder)에도 같은 내용이 있지만, 운영은 db:seed 를 다시
 * 돌리지 않으므로 마이그레이션으로도 넣어 배포만으로 반영되게 한다.
 * updateOrInsert 라 시더와 겹쳐도 중복되지 않는다.
 */
return new class extends Migration
{
    private array $rows = [
        [
            'event_code' => 'return.requested',
            'channel'    => 'sms',
            'name'       => '반품 접수 알림(총판·영업자)',
            'body'       => "[BookSys] 반품이 접수되었습니다.\n반품번호: #{return_no}\n학원: #{vendor_name}\n수량: #{total_qty}권 (#{total_amount}원)\n사유: #{reason}\n확인 후 처리해 주세요.",
            'variables'  => ['return_no', 'vendor_name', 'total_qty', 'total_amount', 'reason'],
        ],
        [
            'event_code' => 'return.confirmed',
            'channel'    => 'sms',
            'name'       => '반품 확정 알림(학부모)',
            'body'       => "[BookSys] 신청하신 반품이 확정되었습니다.\n반품번호: #{return_no}\n수량: #{total_qty}권\n#{refund_note}",
            'variables'  => ['return_no', 'total_qty', 'refund_note'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->rows as $r) {
            DB::table('notification_templates')->updateOrInsert(
                ['event_code' => $r['event_code'], 'channel' => $r['channel']],
                [
                    'name'       => $r['name'],
                    'subject'    => null,
                    'body'       => $r['body'],
                    'variables'  => json_encode($r['variables']),
                    'is_active'  => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        foreach ($this->rows as $r) {
            DB::table('notification_templates')
                ->where('event_code', $r['event_code'])
                ->where('channel', $r['channel'])
                ->delete();
        }
    }
};
