<?php

use App\Models\SiteSetting;

if (! function_exists('setting')) {
    /**
     * 사이트 설정 값 조회
     */
    function setting(string $key, ?string $default = null): ?string
    {
        $all = SiteSetting::cached();
        $value = $all[$key] ?? null;
        if ($value === null || $value === '') {
            return $default;
        }
        return $value;
    }
}

if (! function_exists('format_phone')) {
    /**
     * 전화번호 포맷: 01010000002 → 010-1000-0002, 0212345678 → 02-1234-5678
     * 숫자만 추출 후 길이에 따라 분리. 이미 하이픈이 있으면 정규화해서 다시 포맷.
     */
    function format_phone(?string $phone): string
    {
        if ($phone === null || $phone === '') return '';
        $d = preg_replace('/[^0-9]/', '', $phone);
        if (! $d) return '';

        // 휴대폰 / 인터넷 전화
        if (preg_match('/^(010|011|016|017|018|019|050\d|070)/', $d)) {
            // 010-XXXX-XXXX (11자) 또는 010-XXX-XXXX (10자)
            if (strlen($d) === 11) return preg_replace('/^(\d{3})(\d{4})(\d{4})$/', '$1-$2-$3', $d);
            if (strlen($d) === 10) return preg_replace('/^(\d{3})(\d{3})(\d{4})$/', '$1-$2-$3', $d);
            if (strlen($d) === 12) return preg_replace('/^(\d{4})(\d{4})(\d{4})$/', '$1-$2-$3', $d);
        }
        // 서울 (02)
        if (str_starts_with($d, '02')) {
            if (strlen($d) === 10) return preg_replace('/^(02)(\d{4})(\d{4})$/', '$1-$2-$3', $d);
            if (strlen($d) === 9)  return preg_replace('/^(02)(\d{3})(\d{4})$/', '$1-$2-$3', $d);
        }
        // 기타 지역번호 (031, 032, …)
        if (strlen($d) === 11) return preg_replace('/^(\d{3})(\d{4})(\d{4})$/', '$1-$2-$3', $d);
        if (strlen($d) === 10) return preg_replace('/^(\d{3})(\d{3})(\d{4})$/', '$1-$2-$3', $d);

        return $d; // fallback
    }
}

if (! function_exists('setting_image')) {
    /**
     * 이미지 설정 (storage path 처리)
     */
    function setting_image(string $key, ?string $default = null): ?string
    {
        $path = setting($key, $default);
        if (! $path) return null;
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return $path;
        }
        return '/storage/' . ltrim($path, '/');
    }
}
