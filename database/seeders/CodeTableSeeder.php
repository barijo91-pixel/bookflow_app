<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CodeTableSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            // 사용자
            ['user_role', '사용자그룹', '시스템관리자/총판/영업자/학원', true],
            ['user_status', '사용자상태', '승인/대기/거래종료', true],
            ['admin_level', '관리자 권한 단계', '슈퍼/일반', true],
            // 학교/학년/학기/난이도/대상
            ['school', '학교분류', '초/중/고/단행본', true],
            ['grade', '학년', '예비초~고3', true],
            ['semester', '학기', '1학기/2학기', true],
            ['level', '난이도', '입문/기초/심화 등', true],
            ['audience', '학습대상', '학생/학부모/교사 등', true],
            // 도서/과목
            ['book_status', '도서상태', '판매중/일시중지/절판/출간예정', true],
            ['subject', '과목분류', '국어/영어/수학 등', true],
            // 거래처
            ['vendor_type', '거래처구분', '학원/일반 등', true],
            ['vendor_status', '거래처상태', '정상/일시정지/거래종료', true],
            // 주문
            ['order_status', '주문상태', '접수→완료', true],
            ['ship_status', '배송상태', '준비/출고/배송중/완료', true],
            // 결제(추후)
            ['payment_method', '결제수단', '카드/계좌이체/카카오페이 등', true],
            ['payment_status', '결제상태', '대기/완료/취소', true],
            // 세금계산서(추후)
            ['invoice_status', '세금계산서상태', '발행대기/발행/취소', true],
            // 알림/감사
            ['notify_channel', '알림채널', '알림톡/SMS/푸시/이메일', true],
            ['notify_event', '알림이벤트', '회원/주문 트리거', true],
            // 은행/택배
            ['bank', '은행코드', '은행 목록', true],
            ['courier', '택배사', '택배사 목록', true],
        ];

        foreach ($groups as $i => [$code, $name, $desc, $sys]) {
            DB::table('code_groups')->updateOrInsert(
                ['group_code' => $code],
                [
                    'name' => $name,
                    'description' => $desc,
                    'is_system' => $sys,
                    'is_active' => true,
                    'sort_order' => ($i + 1) * 10,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $codes = [
            // user_role
            ['user_role', 'admin', '시스템관리자', null, 10],
            ['user_role', 'distributor', '총판', null, 20],
            ['user_role', 'agent', '영업자', null, 30],
            ['user_role', 'academy', '학원(원장/담당)', null, 40],
            // user_status
            ['user_status', 'pending', '승인 대기', null, 10],
            ['user_status', 'active', '승인(정상)', null, 20],
            ['user_status', 'suspended', '일시정지', null, 30],
            ['user_status', 'terminated', '거래종료', null, 40],
            // admin_level
            ['admin_level', 'super', '슈퍼관리자', null, 10],
            ['admin_level', 'staff', '일반관리자', null, 20],
            // school
            ['school', 'elementary', '초등', null, 10],
            ['school', 'middle', '중등', null, 20],
            ['school', 'high', '고등', null, 30],
            ['school', 'general', '단행본', null, 40],
            // grade
            ['grade', 'pre_elem', '예비초', null, 5],
            ['grade', 'elem_1', '초1', null, 10],
            ['grade', 'elem_2', '초2', null, 20],
            ['grade', 'elem_3', '초3', null, 30],
            ['grade', 'elem_4', '초4', null, 40],
            ['grade', 'elem_5', '초5', null, 50],
            ['grade', 'elem_6', '초6', null, 60],
            ['grade', 'mid_1', '중1', null, 70],
            ['grade', 'mid_2', '중2', null, 80],
            ['grade', 'mid_3', '중3', null, 90],
            ['grade', 'high_1', '고1', null, 100],
            ['grade', 'high_2', '고2', null, 110],
            ['grade', 'high_3', '고3', null, 120],
            // semester
            ['semester', 'sem_1', '1학기', null, 10],
            ['semester', 'sem_2', '2학기', null, 20],
            // level
            ['level', 'intro', '입문', null, 10],
            ['level', 'basic', '기초', null, 20],
            ['level', 'inter', '중급', null, 30],
            ['level', 'advanced', '심화', null, 40],
            // audience
            ['audience', 'student', '학생', null, 10],
            ['audience', 'parent', '학부모', null, 20],
            ['audience', 'teacher', '교사', null, 30],
            // book_status
            ['book_status', 'selling', '판매중', null, 10],
            ['book_status', 'paused', '일시중지', null, 20],
            ['book_status', 'discontinued', '절판', null, 30],
            ['book_status', 'upcoming', '출간예정', null, 40],
            // subject
            ['subject', 'korean', '국어', null, 10],
            ['subject', 'english', '영어', null, 20],
            ['subject', 'math', '수학', null, 30],
            ['subject', 'science', '과학', null, 40],
            ['subject', 'social', '사회/한국사', null, 50],
            // vendor_type
            ['vendor_type', 'academy', '학원', null, 10],
            ['vendor_type', 'general', '일반', null, 20],
            // vendor_status
            ['vendor_status', 'active', '정상', null, 10],
            ['vendor_status', 'suspended', '일시정지', null, 20],
            ['vendor_status', 'terminated', '거래종료', null, 30],
            // order_status
            ['order_status', 'requested', '접수', null, 10],
            ['order_status', 'confirmed', '영업자확정', null, 20],
            ['order_status', 'accepted', '총판접수', null, 30],
            ['order_status', 'shipped', '출고', null, 40],
            ['order_status', 'in_transit', '배송중', null, 50],
            ['order_status', 'completed', '완료', null, 60],
            ['order_status', 'canceled', '취소', null, 70],
            ['order_status', 'returned', '반품', null, 80],
            // ship_status
            ['ship_status', 'preparing', '준비중', null, 10],
            ['ship_status', 'shipped', '출고', null, 20],
            ['ship_status', 'in_transit', '배송중', null, 30],
            ['ship_status', 'delivered', '배송완료', null, 40],
            ['ship_status', 'failed', '배송실패', null, 50],
            // payment_method (추후)
            ['payment_method', 'kakaopay', '카카오페이', null, 10],
            ['payment_method', 'tosspay', '토스페이먼츠', null, 20],
            ['payment_method', 'naverpay', '네이버페이', null, 30],
            ['payment_method', 'card', '신용카드', null, 40],
            ['payment_method', 'transfer', '계좌이체', null, 50],
            // payment_status (추후)
            ['payment_status', 'pending', '결제대기', null, 10],
            ['payment_status', 'paid', '결제완료', null, 20],
            ['payment_status', 'canceled', '결제취소', null, 30],
            // invoice_status (추후)
            ['invoice_status', 'wait', '발행대기', null, 10],
            ['invoice_status', 'issued', '발행완료', null, 20],
            ['invoice_status', 'canceled', '취소', null, 30],
            // notify_channel
            ['notify_channel', 'alimtalk', '카카오 알림톡', null, 10],
            ['notify_channel', 'sms', 'SMS/LMS', null, 20],
            ['notify_channel', 'push', '앱 푸시(FCM)', null, 30],
            ['notify_channel', 'email', '이메일', null, 40],
            // notify_event
            ['notify_event', 'user.phone_verify', '회원가입 휴대폰 인증', null, 10],
            ['notify_event', 'user.approval_result', '가입 승인/거절', null, 20],
            ['notify_event', 'order.requested', '학원 주문 접수', null, 30],
            ['notify_event', 'order.confirmed', '영업자 확정', null, 40],
            ['notify_event', 'order.accepted', '총판 접수', null, 50],
            ['notify_event', 'order.shipped', '출고/송장입력', null, 60],
            ['notify_event', 'order.canceled', '주문 취소', null, 70],
            ['notify_event', 'b2c.share_link', '학부모 공유링크 발송', null, 80],
            // bank (주요 17개)
            ['bank', '004', 'KB국민은행', null, 10],
            ['bank', '088', '신한은행', null, 20],
            ['bank', '081', '하나은행', null, 30],
            ['bank', '020', '우리은행', null, 40],
            ['bank', '011', 'NH농협은행', null, 50],
            ['bank', '003', 'IBK기업은행', null, 60],
            ['bank', '023', 'SC제일은행', null, 70],
            ['bank', '027', '한국씨티은행', null, 80],
            ['bank', '031', '대구은행(iM뱅크)', null, 90],
            ['bank', '032', '부산은행', null, 100],
            ['bank', '034', '광주은행', null, 110],
            ['bank', '035', '제주은행', null, 120],
            ['bank', '037', '전북은행', null, 130],
            ['bank', '039', '경남은행', null, 140],
            ['bank', '045', '새마을금고', null, 150],
            ['bank', '048', '신협', null, 160],
            ['bank', '090', '카카오뱅크', null, 170],
            ['bank', '089', '케이뱅크', null, 180],
            ['bank', '092', '토스뱅크', null, 190],
            ['bank', '007', '수협은행', null, 200],
            // courier
            ['courier', 'cj', 'CJ대한통운', null, 10],
            ['courier', 'lotte', '롯데택배', null, 20],
            ['courier', 'hanjin', '한진택배', null, 30],
            ['courier', 'logen', '로젠택배', null, 40],
            ['courier', 'epost', '우체국택배', null, 50],
            ['courier', 'kdexp', '경동택배', null, 60],
            ['courier', 'cvsnet', 'GS Postbox', null, 70],
            ['courier', 'cu', 'CU 편의점택배', null, 80],
            ['courier', 'direct', '직접배송', null, 90],
        ];

        foreach ($codes as [$group, $code, $name, $value, $sort]) {
            DB::table('codes')->updateOrInsert(
                ['group_code' => $group, 'code' => $code],
                [
                    'name' => $name,
                    'value' => $value,
                    'sort_order' => $sort,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
