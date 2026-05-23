<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            // event_code, channel, name, body, variables
            ['user.phone_verify', 'sms', '회원가입 휴대폰 인증',
                "[BookSys] 인증번호 #{code}\n5분 이내에 입력해주세요.",
                ['code']],

            ['user.approval_result', 'alimtalk', '가입 승인/거절 결과',
                "[BookSys] #{name}님, 가입 신청이 #{result}되었습니다.\n자세한 내용은 앱에서 확인해주세요.",
                ['name','result']],

            ['order.requested', 'alimtalk', '학원 주문 접수(영업자 알림)',
                "[BookSys] 신규 주문이 접수되었습니다.\n주문번호: #{order_no}\n학원: #{vendor_name}\n총액: #{total_amount}원",
                ['order_no','vendor_name','total_amount']],
            ['order.requested', 'push', '학원 주문 접수 푸시',
                "신규 주문: #{vendor_name} (#{total_amount}원)",
                ['vendor_name','total_amount']],

            ['order.confirmed', 'alimtalk', '영업자 확정',
                "[BookSys] 주문이 확정되었습니다.\n주문번호: #{order_no}\n영업자: #{agent_name}",
                ['order_no','agent_name']],
            ['order.confirmed', 'push', '영업자 확정 푸시',
                "주문 확정: #{order_no}",
                ['order_no']],

            ['order.accepted', 'alimtalk', '총판 접수',
                "[BookSys] 총판에서 주문을 접수했습니다.\n주문번호: #{order_no}\n총판: #{distributor_name}",
                ['order_no','distributor_name']],

            ['order.shipped', 'alimtalk', '출고/송장입력',
                "[BookSys] 주문이 출고되었습니다.\n주문번호: #{order_no}\n택배사: #{courier_name}\n송장번호: #{tracking_no}",
                ['order_no','courier_name','tracking_no']],

            ['order.canceled', 'alimtalk', '주문 취소',
                "[BookSys] 주문이 취소되었습니다.\n주문번호: #{order_no}\n사유: #{reason}",
                ['order_no','reason']],

            ['b2c.share_link', 'alimtalk', '학부모 공유링크',
                "[BookSys] #{academy_name}에서 교재 안내를 보내드립니다.\n자녀: #{student_name}\n링크: #{link_url}",
                ['academy_name','student_name','link_url']],
            ['b2c.share_link', 'sms', '학부모 공유링크 SMS 폴백',
                "[BookSys] #{academy_name} 교재 안내: #{link_url}",
                ['academy_name','link_url']],
        ];

        foreach ($templates as [$event, $channel, $name, $body, $vars]) {
            DB::table('notification_templates')->updateOrInsert(
                ['event_code' => $event, 'channel' => $channel],
                [
                    'name' => $name,
                    'subject' => null,
                    'body' => $body,
                    'variables' => json_encode($vars),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
