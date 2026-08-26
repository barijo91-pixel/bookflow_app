{{-- 정렬 가능한 테이블 헤더 링크 (공통) — 모든 admin/mypage 목록 페이지에서 재사용 --}}
@props(['field', 'label', 'sort' => null, 'dir' => 'desc'])
@php
    $active  = $sort === $field;
    $nextDir = ($active && $dir === 'asc') ? 'desc' : 'asc';
    // 정렬 아이콘은 붉은색 — 눌러서 정렬할 수 있다는 걸 눈에 띄게
    $icon    = $active
        ? '<i class="bi bi-caret-' . ($dir === 'asc' ? 'up' : 'down') . '-fill small ms-1 text-danger"></i>'
        : '<i class="bi bi-arrow-down-up small ms-1 text-danger opacity-75"></i>';
    $url     = request()->fullUrlWithQuery(['sort' => $field, 'dir' => $nextDir, 'page' => 1]);
    $cls     = 'text-decoration-none ' . ($active ? 'navy fw-bold' : 'text-dark');
@endphp
<a href="{{ $url }}" class="{{ $cls }}">{{ $label }}{!! $icon !!}</a>
