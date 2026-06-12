<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SiteSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // group, key, value, type, label, description, sort
            ['company', 'company_name', 'e-Learn', 'text', '회사명', null, 10],
            ['company', 'service_name', 'BookSys', 'text', '서비스명', null, 20],
            ['company', 'business_no', '', 'text', '사업자등록번호', null, 30],
            ['company', 'representative', '', 'text', '대표자', null, 40],
            ['company', 'company_address', '', 'text', '주소', null, 50],
            ['company', 'company_phone', '', 'text', '대표 전화', null, 60],
            ['company', 'company_email', 'no-reply@bookflow.local', 'text', '대표 이메일', null, 70],

            // 외부 연동
            ['integration', 'aligo_api_key', '', 'text', '알리고 API Key', null, 10],
            ['integration', 'aligo_user_id', '', 'text', '알리고 User ID', null, 20],
            ['integration', 'aligo_sender_key', '', 'text', '알리고 발신프로필 Key', null, 30],
            ['integration', 'aligo_sender', '', 'text', '발신번호', null, 40],
            ['integration', 'aligo_admin_phone', '', 'text', '관리자 알림 수신번호', null, 50],
            ['integration', 'aladin_ttb_key', '', 'text', '알라딘 TTB API Key', null, 60],
            ['integration', 'kakao_client_id', '', 'text', '카카오 OAuth Client ID', null, 70],
            ['integration', 'kakao_client_secret', '', 'text', '카카오 OAuth Secret', null, 80],
            ['integration', 'fcm_server_key', '', 'textarea', 'FCM Server Key', null, 90],
            ['integration', 'fcm_project_id', '', 'text', 'FCM Project ID', null, 100],
            // PortOne (구 아임포트) PG 결제
            ['integration', 'portone_active', '0', 'boolean', 'PortOne PG 활성화', '미체크 시 mock 결제로 fallback', 110],
            ['integration', 'portone_imp_uid', '', 'text', 'PortOne 가맹점 식별코드 (imp_uid)', '예: imp00000000', 120],
            ['integration', 'portone_rest_api_key', '', 'text', 'PortOne REST API Key', null, 130],
            ['integration', 'portone_rest_secret', '', 'text', 'PortOne REST API Secret', null, 140],

            // SEO
            ['seo', 'meta_title', 'BookSys - 교재 도매 유통 플랫폼', 'text', 'SEO 제목', null, 10],
            ['seo', 'meta_description', '총판·영업자·학원·학부모를 연결하는 교재 유통 올인원 솔루션', 'textarea', 'SEO 설명', null, 20],
            ['seo', 'meta_keywords', '교재,도매,유통,학원,영어교재,총판,영업자', 'text', '키워드', null, 30],

            // 정책
            ['policy', 'phone_verify_ttl', '300', 'number', '휴대폰 인증 만료(초)', '기본 5분', 10],
            ['policy', 'phone_verify_resend_limit', '5', 'number', '재발송 일일 한도', null, 20],
            ['policy', 'order_no_prefix', 'BF', 'text', '주문번호 접두', null, 30],

            // 앱 다운로드
            ['app', 'app_download_active', '0', 'boolean', '앱 다운로드 노출', '메인 페이지에 다운로드 섹션 표시 여부', 10],
            ['app', 'app_download_label', 'BookSys 모바일 앱', 'text', '다운로드 섹션 제목', null, 20],
            ['app', 'app_download_description', '바코드 스캔 주문, 푸시 알림 등 모바일 앱에서만 가능한 기능을 사용하세요.', 'textarea', '다운로드 섹션 설명', null, 30],
            ['app', 'app_download_android_url', '', 'text', 'Android 다운로드 URL', '.apk 파일 URL 또는 Play Store 링크', 40],
            ['app', 'app_download_android_version', '', 'text', 'Android 버전', '예: 1.0.0', 50],
            ['app', 'app_download_ios_url', '', 'text', 'iOS 다운로드 URL', 'App Store 또는 TestFlight 링크', 60],
            ['app', 'app_download_ios_version', '', 'text', 'iOS 버전', '예: 1.0.0', 70],
        ];

        foreach ($settings as [$group, $key, $value, $type, $label, $desc, $sort]) {
            $existing = DB::table('site_settings')->where('key', $key)->first();
            if ($existing) {
                // 메타데이터(group/type/label/description/sort_order)만 갱신, value는 보존
                DB::table('site_settings')->where('key', $key)->update([
                    'group'       => $group,
                    'type'        => $type,
                    'label'       => $label,
                    'description' => $desc,
                    'sort_order'  => $sort,
                    'updated_at'  => now(),
                ]);
            } else {
                DB::table('site_settings')->insert([
                    'group'       => $group,
                    'key'         => $key,
                    'value'       => $value,
                    'type'        => $type,
                    'label'       => $label,
                    'description' => $desc,
                    'sort_order'  => $sort,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }
        }
    }
}
