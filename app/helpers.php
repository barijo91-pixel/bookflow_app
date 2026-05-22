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
