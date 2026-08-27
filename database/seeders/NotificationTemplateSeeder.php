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

            ['payment.requested', 'alimtalk', '학부모 결제 요청',
                "[BookSys] #{vendor_name}에서 #{student_name} 학생 교재 결제 요청드립니다.\n금액: #{amount_fmt}원\n결제하기: #{pay_url}",
                ['vendor_name','student_name','parent_name','amount_fmt','pay_url']],
            ['payment.requested', 'sms', '학부모 결제 요청 SMS 폴백',
                "[BookSys] #{vendor_name} 교재 #{amount_fmt}원 결제: #{pay_url}",
                ['vendor_name','amount_fmt','pay_url']],

            // 반품 — 알림톡 템플릿은 카카오 승인이 필요해 우선 문자(SMS)로 보낸다.
            // 승인 나면 같은 event_code 로 alimtalk 행을 추가하면 둘 다 나간다.
            ['return.requested', 'sms', '반품 접수 알림(총판·영업자)',
                "[BookSys] 반품이 접수되었습니다.\n반품번호: #{return_no}\n학원: #{vendor_name}\n수량: #{total_qty}권 (#{total_amount}원)\n사유: #{reason}\n확인 후 처리해 주세요.",
                ['return_no','vendor_name','total_qty','total_amount','reason']],

            ['return.confirmed', 'sms', '반품 확정 알림(학부모)',
                "[BookSys] 신청하신 반품이 확정되었습니다.\n반품번호: #{return_no}\n수량: #{total_qty}권\n#{refund_note}",
                ['return_no','total_qty','refund_note']],
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
