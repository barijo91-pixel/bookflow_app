<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 알라딘 TTB API 래퍼
 * - ItemLookUp: ISBN13 단권 조회
 * - ItemSearch: 키워드 검색
 *
 * 응답을 BookFlow 도서 스키마로 정규화하여 반환.
 */
class AladinService
{
    private const LOOKUP_URL = 'https://www.aladin.co.kr/ttb/api/ItemLookUp.aspx';
    private const SEARCH_URL = 'https://www.aladin.co.kr/ttb/api/ItemSearch.aspx';
    private const VERSION    = '20131101';

    public function configured(): bool
    {
        return ! empty($this->ttbKey());
    }

    private function ttbKey(): ?string
    {
        return setting('aladin_ttb_key') ?: env('ALADIN_TTB_KEY');
    }

    /**
     * ISBN13으로 단권 조회. 못 찾으면 null 반환.
     *
     * @return array|null  ['isbn','title','subtitle','author','publisher','price','pub_date','cover','description','category']
     */
    public function lookupByIsbn(string $isbn): ?array
    {
        $key = $this->ttbKey();
        if (! $key) {
            return null;
        }
        $isbn = preg_replace('/[^0-9Xx]/', '', $isbn);
        if (! $isbn) return null;

        try {
            $response = Http::timeout(10)->get(self::LOOKUP_URL, [
                'TTBKey'     => $key,
                'ItemId'     => $isbn,
                'ItemIdType' => 'ISBN13',
                'Cover'      => 'Big',
                'Output'     => 'JS',
                'Version'    => self::VERSION,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Aladin ItemLookUp failed', ['isbn' => $isbn, 'error' => $e->getMessage()]);
            return null;
        }

        if (! $response->ok()) {
            Log::warning('Aladin ItemLookUp non-200', ['status' => $response->status(), 'body' => $response->body()]);
            return null;
        }

        $data = $response->json();
        if (! $data || empty($data['item']) || ! is_array($data['item'])) {
            return null;
        }
        return $this->mapItem($data['item'][0]);
    }

    /**
     * 키워드 검색 (제목/저자/출판사 등)
     *
     * @return array list of mapped items
     */
    public function search(string $query, int $maxResults = 10): array
    {
        $key = $this->ttbKey();
        if (! $key || trim($query) === '') {
            return [];
        }

        try {
            $response = Http::timeout(10)->get(self::SEARCH_URL, [
                'TTBKey'     => $key,
                'Query'      => $query,
                'QueryType'  => 'Keyword',
                'MaxResults' => max(1, min(50, $maxResults)),
                'start'      => 1,
                'SearchTarget'=> 'Book',
                'Cover'      => 'Big',
                'Output'     => 'JS',
                'Version'    => self::VERSION,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Aladin ItemSearch failed', ['query' => $query, 'error' => $e->getMessage()]);
            return [];
        }

        if (! $response->ok()) return [];
        $data = $response->json();
        if (! $data || empty($data['item'])) return [];

        return array_map(fn ($it) => $this->mapItem($it), $data['item']);
    }

    /**
     * 알라딘 응답 1건을 우리 스키마로 매핑
     */
    private function mapItem(array $it): array
    {
        $isbn = (string) ($it['isbn13'] ?? $it['isbn'] ?? '');
        $price = (int) ($it['priceStandard'] ?? 0);
        $title = (string) ($it['title'] ?? '');
        // 알라딘 title은 종종 "본제목 - 부제목" 형태
        $subtitle = '';
        if (str_contains($title, ' - ')) {
            [$title, $subtitle] = array_map('trim', explode(' - ', $title, 2));
        }

        return [
            'isbn'        => $isbn,
            'title'       => $title,
            'subtitle'    => $subtitle,
            'author'      => (string) ($it['author'] ?? ''),
            'publisher'   => (string) ($it['publisher'] ?? ''),
            'price'       => $price,
            'pub_date'    => (string) ($it['pubDate'] ?? ''),
            'cover'       => (string) ($it['cover'] ?? ''),
            'description' => (string) ($it['description'] ?? ''),
            'category'    => (string) ($it['categoryName'] ?? ''),
            'link'        => (string) ($it['link'] ?? ''),
            'raw'         => $it,
        ];
    }
}
